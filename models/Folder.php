<?php

namespace app\models;

use Yii;
use yii\base\Exception;

/**
 * This is the model class for table "folder".
 *
 * @property integer $id
 * @property string $client_key
 * @property string $folder_name
 * @property string $folderNameAbsolute
 * @property integer $unixtime
 * @property string $status
 * @property integer $progress
 */
class Folder extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'folder';
    }

    public function getFolderNameAbsolute()
    {
        return \Yii::getAlias('@webroot') . '/' . \Yii::$app->params['uploadDir'] . "/{$this->folder_name}";
    }

    private static function removeDirectory($dir) { //рекурсивное удаление папки
        if ($objs = glob($dir."/*"))
            foreach($objs as $obj)
                if(is_dir($obj))
                    self::removeDirectory($obj);
                else
                    if(!unlink($obj))
                        throw new Exception('Error on delete file: '.$obj, 500);

        if(!rmdir($dir))
            throw new Exception('Error on delete dir: '.$dir, 500);

    }

    public static function cleanOldFolders() {//очистка старых слайдеров
        $expiredSecondsAgo = 30*60; //30 минут назад
        $folders = self::find()->where(['<','unixtime',(time()-$expiredSecondsAgo)])->all(); //ищем все, что старее 30 минут назад.

        foreach ($folders as $f) {
            if(file_exists($f->folderNameAbsolute))
                self::removeDirectory($f->folderNameAbsolute); // чистим рекурсивно каталог
            if(!$f->delete()) //удаляем запись
                throw new Exception('Error on delete folder row', 500);

        }
    }

    public function getFiles() { //получение списка файлов массивом с путями относительно корня сайта
        $filenames = $this->fileNames;
        $result = [];
        foreach ($filenames as $f)
            $result[] = \Yii::getAlias('@web') . '/' . \Yii::$app->params['uploadDir'] . "/{$this->folder_name}/images/" . $f;
        return $result;
    }

    public function getFileNames() { //получение списка файлов массивом
        $scanDir = "{$this->folderNameAbsolute}/images";
        if (file_exists($scanDir)) {
            $files = array_diff(scandir($scanDir), ['..', '.']);
            $result = [];
            foreach ($files as $f)
                $result[] = $f;
            return $result;
        }
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['client_key', 'folder_name', 'unixtime', 'status'], 'required'],
            [['client_key', 'folder_name', 'status'], 'string'],
            [['unixtime', 'progress'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'client_key' => 'Client Key',
            'folder_name' => 'Folder Name',
            'unixtime' => 'Unixtime',
            'status' => 'Status',
            'progress' => 'Progress',
        ];
    }
}
