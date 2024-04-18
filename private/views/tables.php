<?php
	
	$translation = $_SESSION['translation'];
?>

<div class="main-header">
  <div class="person">
    <div class="person-details">
      <div class="person-name"><?php echo $translation["promptSuggestion"]; ?></div>
      <div class="suggestion">
        <?php echo $translation["tableCreationAssistanceRequest"]; ?>
      </div>
    </div>
  </div>

  <svg class="copy-to-input-icon" onclick="copyToInput('.suggestion')" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
    <g>
      <path d="M12,2c5.5,0,10,4.5,10,10s-4.5,10-10,10S2,17.5,2,12S6.5,2,12,2z"  />
      <path id="input-send-icon" d="M 16 12 l -4 4 l -4 -4 M 12 16 V 8"/>
    </g>
  </svg>
</div>

<div class="message me" data-role="system">
  <div class="message-content">
    <div class="message-icon"><?php echo $translation["system"]; ?></div>
    <div class="message-text">
      <?php echo $translation["tableExpertiseMessage"]; ?>
    </div>
  </div>
</div>
