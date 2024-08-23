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
                <p>
                    <?php echo $translation["usage_guideline_confirm_request"]; ?>
                    <div class="modal-buttons-bar">
                        <button onclick="window.location.href='logout'"><?php echo $translation["disagree"]; ?></button>
                        <button onclick="modalClick(this)" ><?php echo $translation["agree"]; ?></button>
                    </div>
                </p>
                <br>
            </div>
        </div>
	</div>
</div>
