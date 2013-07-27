<?php
/**
 * Template Name: Quiz
 */
function showScore($questions, $results) {
	$return = '<br /><h3>Wynik:</h3><br />';
	$score = 0;
	foreach($results as $key => $result) {
		if ($questions[$result['q']]['q'] && $questions[$result['q']]['a'][$result['a']]) {
			$return .= \"".$questions[$result['q']]['q']."\" - twoja odpowiedź: \"".$questions[$result['q']]['a'][$result['a']]."\"<br />";
			$return .= "Zdobyte punkty: ".$questions[$result['q']]['p'][$result['a']]."<br /><br />";
			$score += $questions[$result['q']]['p'][$result['a']];
		}
	}
	$return .= "Twój wynik: ".$score;
	return $return;
}
function page_quiz_content() {
	$questionsJson = '
		{
			"1": {
				"q": "Czy chcesz wypełnić antietę?",
				"a": {
					"1": "tak",
					"2": "nie"
				},
				"p": {
					"1": 5,
					"2": 10
				}
			},
			"2": {
				"q": "Czy podoba ci się blog?",
				"a": {
					"1": "tak",
					"2": "nie",
					"3": "nie wiem"
				},
				"p": {
					"1": 5,
					"2": 10,
					"3": 15
				}
			},
			"3": {
				"q": "Co najbardziej lubisz na blogu?",
				"a": {
					"1": "teksty",
					"2": "grafiki",
					"3": "dźwięk",
					"4": "video"
				},
				"p": {
					"1": 5,
					"2": 10,
					"3": 15,
					"4": 20
				}
			}
		}
	';
	$questions = json_decode($questionsJson, true);
	$questionNumber = $_GET['q'] + 1; ?>
	<? if(questions): ?>
		<form method="post" action="?q=<?=$questionNumber?>" name="quizform" onsubmit="return(function(el){for (var i=el.length;i--;) {if (el[i].checked) {return true;}}alert('ERROR');return false;})(document.quizform.answer)">
			<? if (!$_GET['q']): ?>
				<input type="submit" value="START QUIZ" />
			<? else: ?>
				<? if ($questions[$_GET['q']]['q']): ?>
					<p><?= $questions[$_GET['q']]['q'] ?></p>
					<input type="hidden" name="questionNumber" value="<?= $_GET['q'] ?>" />
					<?
						$historyArr = unserialize(stripslashes($_POST['history']));
						if (!$historyArr) {
							$historyArr = array();
						}
						array_push($historyArr, array('q' => $_POST['questionNumber'], 'a' => $_POST['answer']));
					?>
					<input type="hidden" name="history" value='<?= serialize($historyArr); ?>' />
					<? foreach($questions[$_GET['q']]['a'] as $k => $q): ?>
						<label>
							<input type="radio" name="answer" value="<?= $k ?>"><?= $q ?>
						</label><br />
					<? endforeach ?>
					<input type="submit" value="next" />
				<? else: ?>
					<?
						$historyArr = unserialize(stripslashes($_POST['history']));
						if (!$historyArr) {
							$historyArr = array();
						}
						array_push($historyArr, array('q' => $_POST['questionNumber'], 'a' => $_POST['answer']));
						echo showScore($questions, $historyArr);
					?>
				<? endif ?>
			<? endif ?>
		</form>
	<? else: ?>
		Wrong questions json array!
	<? endif ?>
<?php }
