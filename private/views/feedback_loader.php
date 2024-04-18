<?php 

if (session_status() == PHP_SESSION_NONE) {
    
}
require_once BOOTSTRAP_PATH;

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    exit;
}

$dir = RESOURCES_PATH . 'feedback/';
$feedbackDB = $dir . 'feedback_db.json';

// Read existing JSON data from the feedback DB file
$feedbackData = [];
if (file_exists($feedbackDB)) {
    $jsonContent = file_get_contents($feedbackDB);
    if (!empty($jsonContent)) {
        $feedbackData = json_decode($jsonContent, true);
        // Check if the decoded data is an array
		
        if (!is_array($feedbackData)) {
            // Handle the case of invalid JSON data
            // You can add logging or handle it according to your application's logic

			echo "<div class='message'>
					<h3>
						There are still no feedbacks...
					</h3>
				</div>";
        }
    }
	else{
		echo    
		"<div class='message'>
			<div class='feedback-content'>			
				<h3>
					There are still no feedbacks...
				</h3>
			</div>		
		</div>";
	}
}

// If $feedbackData is empty or not an array, set it as an empty array
if (!is_array($feedbackData)) {
    $feedbackData = [];
}

// Loop through each feedback entry
foreach ($feedbackData as $feedback) {
    $role = $feedback['role'];
    $content = $feedback['content'];
    $up = $feedback['up'] ?? 0;
    $down = $feedback['down'] ?? 0;
    $id = $feedback['id'];

    echo "<div class='message'>
             <div class='feedback-content'>
                 <div class='message-icon'>$role</div>
                 <div class='message-text'>$content</div>
                 <div class='vote' onclick='SubmitVote(this, \"upvote\")' data-id='$id'><svg xmlns='http://www.w3.org/2000/svg' height='48' viewBox='0 96 960 960' width='48'><path d='M716 936H272V424l278-288 39 31q6 5 9 14t3 22v10l-45 211h299q24 0 42 18t18 42v81.839q0 7.161 1.5 14.661T915 595L789 885q-8.878 21.25-29.595 36.125Q738.689 936 716 936Zm-384-60h397l126-299v-93H482l53-249-203 214v427Zm0-427v427-427Zm-60-25v60H139v392h133v60H79V424h193Z'/></svg><span>$up</span></div>
                 <div class='vote' onclick='SubmitVote(this, \"downvote\")' data-id='$id'><svg xmlns='http://www.w3.org/2000/svg' height='48' viewBox='0 96 960 960' width='48'><path d='M242 216h444v512l-278 288-39-31q-6-5-9-14t-3-22v-10l45-211H103q-24 0-42-18t-18-42v-81.839Q43 579 41.5 571.5T43 557l126-290q8.878-21.25 29.595-36.125Q219.311 216 242 216Zm384 60H229L103 575v93h373l-53 249 203-214V276Zm0 427V276v427Zm60 25v-60h133V276H686v-60h193v512H686Z'/></svg><span>$down</span></div>
             </div>
           </div>";
}

?>

<script>
    let messages = document.querySelectorAll(".message");

    messages.forEach((message)=>{
        let voteButtons = message.querySelectorAll(".vote")

        voteButtons.forEach((voteButton)=>{
            console.log('checking ' + voteButton.dataset.id);

            if(localStorage.getItem(voteButton.dataset.id)){
                return
            }else{
                voteButton.classList.add("vote-hover");
            }
        })

    })
</script>

<style>
    .input-container{
        display: none;
    }

    .userpost-container{
        display: block;
    }

    .message:nth-child(odd){
        background: transparent;
    }
</style>
