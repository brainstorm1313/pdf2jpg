<div id="slidr-div" style="dislay: block">
    <?php
    foreach ($images as $i => $img) {
        echo "<img data-slidr='$i' src='$img'/>";
    }
    ?>
</div>
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function () { //$(document).ready() без jquery

        slidr.create('slidr-div',{
            breadcrumbs: true,
            fade: false,
            keyboard: true,
            overflow: true,
            pause: false,
            theme: '#222',
            touch: true}).start();

    });
</script>