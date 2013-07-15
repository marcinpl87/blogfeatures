<?php
/**
 * Template Name: Quiz
 */
function page_quiz_content() { ?>
  <? $questionNumber = $_GET['q'] + 1; ?>
	<form method="post" action="?q=<?=$questionNumber?>">
		<? if (!$_GET['q']): ?>
			<input type="submit" value="START QUIZ" />
		<? else: ?>
			<p>Question <?=$_GET['q']?></p>
			<label>
				<input type="radio" name="question" />Answer 1
			</label><br />
			<label>
				<input type="radio" name="question" />Answer 2
			</label><br />
			<label>
				<input type="radio" name="question" />Answer 3
			</label><br />
			<label>
				<input type="radio" name="question" />Answer 4
			</label><br />
			<input type="submit" value="next" />
		<? endif ?>
	</form>
<?php }
