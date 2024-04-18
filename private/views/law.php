<?php
	
	$translation = $_SESSION['translation'];
?>

<div class="main-header">
  <div class="person">
    <img src="public/img/AvatarRechtsberatung.png" alt="" />
    <div class="person-details">
      <div class="person-name">Luis</div>
      <div class="person">Martinez</div>
    </div>
  </div>

  <div class="suggestion">
    <?php echo $translation["lawDialogueIntroduction"]; ?>
  </div>
</div>

<div class="modal" id="law-confirm">
  <div class="modal-panel">
    <div class="modal-content">
      <h2><?php echo $translation["usageNoticeTitle"]; ?></h2>
      <p>
        <?php echo $translation["lawUsageNoticeContent"]; ?>
      </p>
      <button onclick="modalClick(this)"><?php echo $translation["confirmButton"]; ?></button>
    </div>
  </div>
</div>

<div class="message me" data-role="system">
  <div class="message-content">
    <div class="message-icon"><?php echo $translation["system"]; ?></div>
    <div class="message-text">
      <?php echo $translation["lawExpertiseMessage"]; ?>
    </div>
  </div>
</div>
