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
					"1": "tak",
					"2": "nie"
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
		<form method="post" action="?q=<?=$questionNumber?>" name="quizform" onsubmit="return(function(el){for (var i=el.length;i--;) {if (el[i].checked) {return true;}}alert('ERROR');return false;})(document.quizform.question)">
			<? if (!$_GET['q']): ?>
				<input type="submit" value="START QUIZ" />
			<? else: ?>
				<? if ($questions[$_GET['q']]['q']): ?>
					<p><?= $questions[$_GET['q']]['q'] ?></p>
					<? foreach($questions[$_GET['q']]['a'] as $q): ?>
						<label>
							<input type="radio" name="question" value="1"><?= $q ?>
						</label><br />
					<? endforeach ?>
					<input type="submit" value="next" />
				<? else: ?>
					End of the quiz!
				<? endif ?>
			<? endif ?>
		</form>
	<? else: ?>
		Wrong questions json array!
	<? endif ?>
<?php }
