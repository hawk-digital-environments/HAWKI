<?php
	
	$translation = $_SESSION['translation'];
?>

<div class="limitations">
	<div>
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.709 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke="#06B044" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		<path d="M22 4L12 14.01L9 11.01" stroke="#06B044" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
		<h2><?php echo $translation["Possibility"]; ?></h2>
		<div>
			<div class="limitation-item">
				<strong><?php echo $translation["ContextComprehension"]; ?></strong><?php echo $translation["ContextComprehension_Info"]; ?>
			</div>
			<div class="limitation-item">
				<strong><?php echo $translation["Iteration"]; ?></strong><?php echo $translation["Iteration_Info"]; ?>
			</div>
			<div class="limitation-item">
				<strong><?php echo $translation["Formatting"]; ?></strong><?php echo $translation["Formatting_Info"]; ?>
			</div>
		 </div>
	</div>
	<div>
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#FF5C00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		<path d="M12 8V12" stroke="#FF5C00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		<path d="M12 16H12.01" stroke="#FF5C00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
		<h2><?php echo $translation["Limitations"]; ?></h2>
		<div>
		   <div class="limitation-item">
			   <strong><?php echo $translation["Incomplete"]; ?></strong><?php echo $translation["Incomplete_Info"]; ?>
		   </div>
		   <div class="limitation-item">
			   <strong><?php echo $translation["Caution"]; ?></strong><?php echo $translation["Caution_Info"]; ?>
		   </div>
		   <div class="limitation-item">
				<strong><?php echo $translation["Limitation"]; ?></strong><?php echo $translation["Limitation_Info"]; ?>
			</div>
		</div>
	</div>
</div>


<div class="message me" data-role="system">
   <div class="message-content">
	   <div class="message-icon"><?php echo $translation["System"]; ?></div>
	   <div class="message-text"><?php echo $translation["System_Content"]; ?></div>
   </div>
</div>

<div class="modal" id="delete-chat-confirm">
  <div class="modal-panel">
    <div class="modal-content">
      	<h3>
        	<?php echo $translation["deleteChat"]; ?>
		</h3>
		<div class="modal-buttons-bar">
			<button onclick="cancelDelete()"><?php echo $translation["cancel"]; ?></button>
			<button onclick="deleteChatLog()"><?php echo $translation["delete"]; ?></button>
		</div>
    </div>
  </div>
</div>
