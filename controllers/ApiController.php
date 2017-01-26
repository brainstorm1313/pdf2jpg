<?php

namespace app\controllers;

use yii\web\Controller;
use app\models\Folder;

class ApiController extends Controller
{

    public function actionFiles($clientKey)
    {
        $folder = Folder::findOne(['client_key' => $clientKey]);
        if ($folder)
            $result = $folder->files;

        if($result)
            return json_encode($result);
        
        $result = ['error' => 'content not found'];
        \Yii::$app->response->statusCode = 404;
        return json_encode($result);
    }

    public function actionStatus($clientKey)
    {
        $folder = Folder::findOne(['client_key' => $clientKey]);
        if ($folder) {
            $result = [
                'status' => $folder->status,
                'progress' => $folder->progress
            ];
            return json_encode($result);
        }

        $result = ['error' => 'content not found'];
        \Yii::$app->response->statusCode = 404;
        return json_encode($result);
    }
}