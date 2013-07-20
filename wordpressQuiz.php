<?php
/**
 * Template Name: Quiz
 */
function page_quiz_content() {
	$questionsJson = '
		{
			"1": {
				"q": "Czy chcesz wypełnić antietę?",
				"a": {
					"1": "tak"
				}
			},
			"2": {
				"q": "Czy podoba ci się blog?",
				"a": {
					"1": "tak",
					"2": "nie",
					"3": "nie wiem"
				}
			},
			"3": {
				"q": "Co najbardziej lubisz na blogu?",
				"a": {
					"1": "teksty",
					"2": "grafiki",
					"3": "dźwięk",
					"4": "video"
				}
			}
		}
	';
	$questions = json_decode($questionsJson, true);
	$questionNumber = $_GET['q'] + 1; ?>
	<? if(questions): ?>
	<form method="post" action="?q=<?=$questionNumber?>">
		<? if (!$_GET['q']): ?>
			<input type="submit" value="START QUIZ" />
		<? else: ?>
			<p><?= $questions[$_GET['q']]['q'] ?></p>
			<? foreach($questions[$_GET['q']]['a'] as $q): ?>
				<label>
					<input type="radio" name="question" /><?= $q ?>
				</label><br />
			<? endforeach ?>
			<input type="submit" value="next" />
		<? endif ?>
	</form>
	<? else: ?>
		Wrong questions json array!
	<? endif ?>
<?php }
