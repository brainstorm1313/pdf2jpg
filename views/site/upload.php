<?php
use yii\widgets\ActiveForm;
?>

<?php $form = ActiveForm::begin(['id' => 'uploadForm','options' => ['enctype' => 'multipart/form-data']]) ?>

<?= $form->field($model, 'pdfFile')->fileInput()->label(false); ?>
<?= $form->field($model, 'clientKey')->hiddenInput(['id'=>'clientKey'])->label(false); ?>

    <button id="submitButton">Submit</button>

<?php ActiveForm::end() ?>

<div id="statusDiv" style="display:none; margin-top: 20px">
    <div class="progress">
        <div class="progress-bar" id="progressbar" style="width: 0%;">
            0%
        </div>
    </div>
    <div>
        Status: <span id="statusText"></span>
    </div>
</div>
<?php
$statusRoute = Yii::$app->urlManager->createUrl('/api/status');
$sliderRoute = Yii::$app->urlManager->createUrl('/site/slider');
$js = <<<EOF

var statusInterval = 300;
var clientKey = 0;
var waiting = false;
function uploadDone() {
    window.location.href = "$sliderRoute/"+clientKey;
};

function setProgress(data) {
    $('#progressbar').html(data.progress+'%');
    $('#progressbar').width(data.progress+'%');
    $('#statusText').html(data.status);
}

function checkStatus() {
    if(waiting)
        $('#statusDiv').slideDown();
        
    $.getJSON( "{$statusRoute}/" + clientKey, function(data) {
          setProgress(data);
        }).fail(function() {
        }).always(function() {
          if(waiting)
              setTimeout(checkStatus, statusInterval);
        });
}

$('#uploadForm').on('beforeSubmit', function(e) {
    $('#submitButton').prop('disabled', true);
    clientKey = Math.round(new Date().getTime()*1000+Math.random()*1000);
    $('#clientKey').val(clientKey);
    waiting = true;
    $.ajax({
        type: "POST",
        data:  new FormData(this),
        processData: false,
        contentType: false,    
        cache: false,            
        success: function (data) {
            uploadDone();
            $('#submitButton').prop('disabled', false);
            waiting = false;
        },
        error: function () {
            $('#submitButton').prop('disabled', false);
            waiting = false;
        }
    });
    
    setTimeout(checkStatus,100);//дадим время бэкенду создать запись
}).on('submit', function(e){
    e.preventDefault();
});
EOF;

$this->registerJS($js);
?>
