<?php
	// 
	$translation = $_SESSION['translation'];
?>

<div class="modal"  id="data-protection"> 
	<div class="modal-panel">
        <div class="modal-content-wrapper">
            <div class="modal-content">
                <h1><?php echo $translation["guideline_Title"]; ?></h1>

                <?php echo $translation["usage_guideline"]; ?>
                <br>
                <button onclick="modalClick(this)" ><?php echo $translation["confirmButton"]; ?></button>

                <br>
                <br>
            </div>
        </div>
	</div>
</div>
