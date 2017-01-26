<?php
use yii\helpers\Html;
?>
<?= Html::jsFile('@web/js/slidr.min.js') ?>

<?=$this->render('_slider', ['images' => $images]) ?>

<?= Html::a('Скачать',['site/download','clientKey'=> $clientKey]) ?>
<br>
<?= Html::a('REST: Cписок файлов',['api/files','clientKey'=> $clientKey]) ?>