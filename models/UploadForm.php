<?php
namespace app\models;

use yii\base\Model;
use yii\base\Exception;
use yii\web\UploadedFile;

class UploadForm extends Model
{
    /**
     * @var UploadedFile
     */
    public $pdfFile;
    public $clientKey;
    public $folderName;


    private static function generateRandomString($length = 5)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function generateFolderName()
    {
        $this->folderName= time().self::generateRandomString();
    }

    public function getFolderNameAbsolute() {
        return  \Yii::getAlias('@webroot').'/'.\Yii::$app->params['uploadDir']."/{$this->folderName}";
    }
    
    public function rules()
    {
        return [
            [['pdfFile'], 'file', 'skipOnEmpty' => false, 'extensions' => 'pdf'],
            [['clientKey'], 'integer'],
            ['clientKey', 'match', 'pattern' => '/^\d*$/i'], //защита от попыток записать что-то не то
        ];
    }

    public function upload()
    {
        if ($this->validate()) {

            $safetyCount=10; //защита от бесконечного цикла
            $dirMaked = false;
            do {//есть оооочень маленькая вероятность дублирования папок. учтем ее и дадим несколько попыток генерации нового имени папки
                $this->generateFolderName(); //генерируем уникальное название папки
                if(!file_exists($this->folderNameAbsolute)) { 
                    if (mkdir($this->folderNameAbsolute)) {
                        mkdir("{$this->folderNameAbsolute}/images");
                        $dirMaked = true;
                    }
                }
                $safetyCount--;
            } while (!$dirMaked && $safetyCount>0);

            if($safetyCount === 0) {
                throw new Exception('Error on making dir', 500);
            }

            $this->pdfFile->saveAs("{$this->folderNameAbsolute}/source.pdf");
            return true;
        }
        throw new Exception('Error on validate on upload file', 500); //от пользователя всегда все должно всегда приходить корректно
    }
}

?>