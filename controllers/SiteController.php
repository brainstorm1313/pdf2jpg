<?php

namespace app\controllers;

use app\models\Files;
use app\models\Folder;
use Yii;
use yii\base\Exception;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\UploadForm;
use yii\web\UploadedFile;

class SiteController extends Controller
{
    /**
     * @inheritdoc
     */

    public $defaultAction = 'upload';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionSlider($clientKey) { //возвращает слайдер
        $images = [];
        $folder = Folder::findOne(['client_key' => $clientKey]);
        if ($folder)
            $images = $folder->files;

        return $this->render('slider',['images' => $images,'clientKey' => $clientKey]);
    }

    public function actionDownload($clientKey) { //формируем ZIP архив и отдаем клиенту

        $folder = Folder::findOne(['client_key' => $clientKey]);
        if (!$folder)
            throw new Exception('No folder found', 500);

        $zipFileName = $folder->folderNameAbsolute . '/slider.zip';

        if(!file_exists($zipFileName)) { //если архив существует, повторно не генерим
            $images = $folder->fileNames;
            $imagesWithPath = [];
            $zip = new \ZipArchive;
            $res = $zip->open($zipFileName,\ZipArchive::CREATE);
            if ( $res === TRUE) {
                $zip->addEmptyDir('js');
                $zip->addFile(\Yii::getAlias('@webroot').'/js/slidr.min.js', 'js/slidr.min.js');
                $zip->addEmptyDir('images');
                foreach ($images as $im) {
                    $imagesWithPath[]= "images/$im";
                    $zip->addFile($folder->folderNameAbsolute . '/images/' . $im, "images/$im");
                }
                $this->layout = 'local';
                if(file_put_contents($folder->folderNameAbsolute.'/index.html'
                                    ,$this->render('_slider',['images' => $imagesWithPath])) === false)
                    throw new Exception('Error on save index.html', 500);

                $zip->addFile($folder->folderNameAbsolute . '/index.html', "index.html");
                $zip->close();
            } else
                throw new Exception('Error on create Zip archive', 500);
        }

        return Yii::$app->response->sendFile($folder->folderNameAbsolute.'/slider.zip', 'slider.zip');
    }


    /**
     * Login action.
     *
     * @return string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return string
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }


    /**
     * Displays contact page.
     *
     * @return string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    public function actionUpload() { //загрузка файла
        Folder::cleanOldFolders();

        $model = new UploadForm();

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $model->pdfFile = UploadedFile::getInstance($model, 'pdfFile');

            $folder = new Folder(); //сохраняем данные для отображение прогрессбара
            $folder->status = 'uploading';
            $folder->progress = 0;
            $folder->folder_name = 'auto';
            $folder->unixtime = time();
            $folder->client_key = $model->clientKey;

            if(!$folder->save(false))
                throw new Exception('Error on save folder', 500);

            if ($model->upload()) {
                $folder->status = 'uploaded';
                $folder->progress = 10;
                $folder->folder_name = $model->folderName;
                if(!$folder->save(false))
                    throw new Exception('Error on save folder', 500);

                $im = new \Imagick("{$model->folderNameAbsolute}/source.pdf");
                $pagesCount = $im->getNumberImages();
                if($pagesCount>Yii::$app->params['pdfMaxPageCount']) {
                    $folder->delete();
                    return json_encode(['error' => "Page's count is too big"]);
                }

                // конвертируем PDF в картинки постранично
                for($i = 0;$i < $pagesCount; $i++) {
                    sleep(1); // для наглядности прогрессбара
                    $folder->status = "processing ".($i+1)."/$pagesCount page";
                    $folder->progress = 10+($i+1)/$pagesCount*(100-10);
                    $folder->save(false); //сохраняем данные для отображение прогрессбара
                    $im->setIteratorIndex($i);
                    if(Yii::$app->params['pdfOriantation']) { //проверим на ориентацию каждую страницу
                        $geo=$im->getImageGeometry();
                        $pdfOriantation = Yii::$app->params['pdfOriantation'];
                        if(    (Yii::$app->params['pdfOriantation'] === 'portrait' && $geo['width']>$geo['height'])
                            || (Yii::$app->params['pdfOriantation'] === 'landscape' && $geo['width']<$geo['height'])
                        ) {
                            $folder->delete();
                            return json_encode(['error' => "Oriantation is not \"$pdfOriantation\""]);
                        }
                    }

                    $im->setImageFormat('jpeg');

                    $im->writeImage("{$model->folderNameAbsolute}/images/".str_pad($i,5,'0',STR_PAD_LEFT).'.jpg');
                }
                $im->destroy();
                return ''; //нет необходимости сообщать о статусе, т.к. прогрессбар запрашивает данные асинхронно
            }
            throw new Exception('Error on upload file', 500);
        }

        return $this->render('upload', ['model' => $model]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }
}
