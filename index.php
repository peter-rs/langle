<?php
$url_to_langle = 'https://peter.rs/projects/langle'; // Adjust if needed (e.g., 'example.com/langle')
$file_name = 'langle_game.db';
$db = new SQLite3($file_name);
if (!$db) 
{
    die("Failed to connect to the database!");
}
$query = $db->prepare("SELECT * FROM daily_phrase ORDER BY date DESC LIMIT 1");
$result = $query->execute();
$data = $result->fetchArray(SQLITE3_ASSOC);
if (!$data) 
    {
    die("No data available!");
}
// get langle number by counting total amount of rows in daily_phrase
$query = $db->prepare("SELECT COUNT(*) FROM daily_phrase");
$result = $query->execute();
$langle_number = $result->fetchArray()[0];
$title = "Langle #$langle_number | " . date("F j, Y", strtotime($data['date']));
// get date by converting the date string from the data to a string in the format "Month Day, Year"
$date = date("F j, Y", strtotime($data['date']));
// initialize session for tracking attempts, guesses, and quizzes
session_set_cookie_params([
    'lifetime' => 30 * 60, 
    'path' => '/',
    'domain' => $domain,
    'secure' => true, 
    'httponly' => true,
]);
session_start();
if (!isset($_SESSION['attempts'])) {
    $_SESSION['attempts'] = 0;
}
if (!isset($_SESSION['correct'])) {
    $_SESSION['correct'] = false;
}
if (!isset($_SESSION['guesses'])) {
    $_SESSION['guesses'] = [];
}
if (!isset($_SESSION['quiz_progress'])) {
    $_SESSION['quiz_progress'] = ['translation' => false, 'country' => false, 'speakers' => false]; // Track progress through the various quiz stages.
}
if (!isset($_SESSION['quiz_feedback'])) {
    $_SESSION['quiz_feedback'] = []; // Store feedback for each quiz
}

// Predefined list of languages with family and continent, doesn't have to match script.py (but every language in script.py must be here)
$language_data = [
    ["language" => "Spanish", "family" => "Romance", "continent" => "Europe"],
    ["language" => "Hindi", "family" => "Indo-European", "continent" => "Asia"],
    ["language" => "Portuguese", "family" => "Romance", "continent" => "Europe"],
    ["language" => "Russian", "family" => "Indo-European", "continent" => "Europe"],
    ["language" => "German", "family" => "Germanic", "continent" => "Europe"],
    ["language" => "Turkish", "family" => "Turkic", "continent" => "Asia"],
    ["language" => "French", "family" => "Romance", "continent" => "Europe"],
    ["language" => "Vietnamese", "family" => "Austroasiatic", "continent" => "Asia"],
    ["language" => "Italian", "family" => "Romance", "continent" => "Europe"],
    ["language" => "Polish", "family" => "West Slavic", "continent" => "Europe"],
    ["language" => "Serbian", "family" => "South Slavic", "continent" => "Europe"],
    ["language" => "Ukrainian", "family" => "East Slavic", "continent" => "Europe"],
    ["language" => "Czech", "family" => "West Slavic", "continent" => "Europe"],
    ["language" => "Swedish", "family" => "Norse", "continent" => "Europe"],
    ["language" => "Danish", "family" => "Norse", "continent" => "Europe"],
    ["language" => "Norwegian", "family" => "Norse", "continent" => "Europe"],
    ["language" => "Finnish", "family" => "Uralic", "continent" => "Europe"],
    ["language" => "Hungarian", "family" => "Uralic", "continent" => "Europe"],
    ["language" => "Romanian", "family" => "Romance", "continent" => "Europe"],
    ["language" => "Dutch", "family" => "Germanic", "continent" => "Europe"],
    ["language" => "Greek", "family" => "Hellenic", "continent" => "Europe"],
    ["language" => "Bulgarian", "family" => "South Slavic", "continent" => "Europe"],
    ["language" => "Slovak", "family" => "West Slavic", "continent" => "Europe"],
    ["language" => "Lithuanian", "family" => "Baltic", "continent" => "Europe"],
    ["language" => "Latvian", "family" => "Baltic", "continent" => "Europe"],
    ["language" => "Estonian", "family" => "Uralic", "continent" => "Europe"],
    ["language" => "Albanian", "family" => "Indo-European", "continent" => "Europe"],
    ["language" => "Croatian", "family" => "South Slavic", "continent" => "Europe"],
    ["language" => "Slovenian", "family" => "South Slavic", "continent" => "Europe"],
    ["language" => "Bosnian", "family" => "South Slavic", "continent" => "Europe"],
    ["language" => "Macedonian", "family" => "South Slavic", "continent" => "Europe"],
    ["language" => "Montenegrin", "family" => "South Slavic", "continent" => "Europe"],
    ["language" => "Icelandic", "family" => "Norse", "continent" => "Europe"],
    ["language" => "Maltese", "family" => "Semitic", "continent" => "Europe"],
    ["language" => "Irish", "family" => "Celtic", "continent" => "Europe"],
    ["language" => "Welsh", "family" => "Celtic", "continent" => "Europe"],
    ["language" => "Luxembourgish", "family" => "Germanic", "continent" => "Europe"],
    ["language" => "Belarussian", "family" => "East Slavic", "continent" => "Europe"],
    ["language" => "Mongolian", "family" => "Mongolic", "continent" => "Asia"],
];

$language_options = array_column($language_data, 'language');
sort($language_options);
// initial country guess handling
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guess'])) {
    $guess = trim($_POST['guess']);

    if (!in_array($guess, $language_options)) {
        $message = "❌ Invalid guess. Please select a language from the list.";
    } else {
        $guessed_language = array_filter($language_data, function ($lang) use ($guess) {
            return $lang['language'] === $guess;
        });
        $guessed_language = array_values($guessed_language)[0];

        $_SESSION['attempts']++;
        $family_feedback = ($guessed_language['family'] === $data['family']) ? "correct" : "incorrect";
        $continent_feedback = ($guessed_language['continent'] === $data['continent']) ? "correct" : "incorrect";

        $_SESSION['guesses'][] = [
            "guess" => $guess,
            "family" => $guessed_language['family'],
            "continent" => $guessed_language['continent'],
            "family_feedback" => $family_feedback,
            "continent_feedback" => $continent_feedback
        ];

        if ($guess === $data['language']) {
            $_SESSION['correct'] = true;
            $_SESSION['correct_language'] = $guess;
            $_SESSION['quiz_progress']['translation'] = true; // start first quiz
            $message = "🎉 Correct! The language is " . $data['language'] . ".";
        } elseif ($_SESSION['attempts'] >= 5) {
            $message = "❌ You've used all your attempts. The correct answer was " . $data['language'] . ".";
            $_SESSION['quiz_progress']['translation'] = true;
        } else {
            $message = "❌ Incorrect. Try again! Attempts remaining: " . (5 - $_SESSION['attempts']);
        }
    }
}

// quiz submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_answer'])) {
    $quiz_type = $_POST['quiz_type'];
    $selected_answer = $_POST['quiz_answer'];

    if ($quiz_type === 'translation') {
        if ($selected_answer === $data['translation']) {
            $_SESSION['quiz_feedback']['translation'] = "🎉 Correct! The translation was: '" . $data['translation'] . "'.";
            $_SESSION['quiz_progress']['country'] = true; // unlock next quiz
        } else {
            $_SESSION['quiz_feedback']['translation'] = "❌ Incorrect. The correct translation was: '" . $data['translation'] . "'.";
            $_SESSION['quiz_progress']['country'] = true;
        }
    } elseif ($quiz_type === 'country') {
        if ($selected_answer === $data['country']) {
            $_SESSION['quiz_feedback']['country'] = "🎉 Correct! The country of origin is: '" . $data['country'] . "'.";
            $_SESSION['quiz_progress']['speakers'] = true; // unlock next quiz
        } else {
            $_SESSION['quiz_feedback']['country'] = "❌ Incorrect. The correct country was: '" . $data['country'] . "'.";
            $_SESSION['quiz_progress']['speakers'] = true;
        }
    } elseif ($quiz_type === 'speakers') {
        if ((int)$selected_answer === $data['spoken_by']) {
            $_SESSION['quiz_feedback']['speakers'] = "🎉 Correct! The number of speakers is: '" . $data['spoken_by'] . "'.";
            $_SESSION['quiz_progress']['done'] = true; // all quizzes complete
        } else {
            $_SESSION['quiz_feedback']['speakers'] = "❌ Incorrect. The correct number of speakers was: '" . $data['spoken_by'] . "'.";
            $_SESSION['quiz_progress']['done'] = true;
        }
    }
}

// generate options for quizzes
function generate_quiz_options($correct_answer, $incorrect_options) {
    $filtered_options = array_filter($incorrect_options, function ($option) use ($correct_answer) {
        return $option !== $correct_answer;
    });
    $random_options = array_rand(array_flip($filtered_options), 3);
    $options = array_merge($random_options, [$correct_answer]);
    shuffle($options);
    return $options;
}

if ($_SESSION['quiz_progress']['translation'] && !isset($_SESSION['quiz_translation_options'])) {
    $_SESSION['quiz_translation_options'] = generate_quiz_options(
        $data['translation'],
        [
            "Hello, how are you?",
            "What time is it?",
            "I love programming.",
            "Can I have a coffee?",
            "Where is the bathroom?",
            "I am learning a new language.",
            "What is your name?",
            "How much does it cost?",
            "I am hungry.",
            "I am lost.",
            "Where is it?",
            "Train station.",
            "Where is the Hospital?",
            "How do I get to the airport?",
            "Where am I?",
            "I am not from here.",
            "I am a tourist.",
            "I am a student.",
            "I am a teacher.",
            "I need a doctor.",
        ]
    );
}

if ($_SESSION['quiz_progress']['country'] && !isset($_SESSION['quiz_country_options'])) {
    $_SESSION['quiz_country_options'] = generate_quiz_options(
        $data['country'],
        [
            "India", "China", "France", "Spain", "Japan", "Germany", "Russia", "Finland", "Italy", "Norway", "Afghanistan", "Netherlands",
            "Iceland", "Hungary", "Greece", "Turkey", "Portugal", "Thailand", "Myanmar", "Mongolia", "Korea", "Slovakia", "Czechia", "Poland",
            "Ukraine", "Lithuania", "Latvia", "Estonia", "Belarus", "Romania", "Bulgaria", "Croatia", "Slovenia", "Serbia", "Montenegro",
        ]
    );
}

if ($_SESSION['quiz_progress']['speakers'] && !isset($_SESSION['quiz_speaker_options'])) {
    $_SESSION['quiz_speaker_options'] = generate_quiz_options(
        $data['spoken_by'],
        [
            "15.3 million", "25.6 million", "30.2 million", "40.8 million", "50.1 million", "60.7 million", "1.3 million",
            "5.6 million", "10.2 million", "20.8 million", "35.1 million", "45.7 million", "55.3 million", "65.9 million",
            "100,300", "500,600", "1.2 million", "2.8 million", "5.1 million", "6.7 million", "7.3 million",
            "100.7 million", "200.6 million", "300.2 million", "400.8 million", "500.1 million", "600.7 million", "700.3 million",
            "272,000", "550,000", "35,000", "1.8 million", "2.1 million", "3.7 million", "4.3 million", "56.44 million"
        ]
    );
}
function generate_sharing_summary()
{
    global $url_to_langle;
    // this is the summary generated when share summary is clicked
    global $title;
    $summary = $title . "\n";

    foreach ($_SESSION['guesses'] as $guess) {
        $summary .= "{";
        $summary .= ($guess['guess'] === $_SESSION['correct_language'] ? "🟩" : "🟥") . "}";
        $summary .= ($guess['family_feedback'] === "correct" ? "🟩" : "🟥");
        $summary .= ($guess['continent_feedback'] === "correct" ? "🟩" : "🟥");
        $summary .= "\n";
    }

    $summary .= "Guesses: " . count($_SESSION['guesses']) . "\n\n";

    $summary .= "Bonus: ";
    $summary .= ($_SESSION['quiz_feedback']['translation'] ? "🟩" : "🟥");
    $summary .= ($_SESSION['quiz_feedback']['country'] ? "🟩" : "🟥");
    $summary .= ($_SESSION['quiz_feedback']['speakers'] ? "🟩" : "🟥");
    $summary .= "\n\n";

    $summary .= "Think you'd do better? Try it. ". $url_to_langle . "\n";

    return $summary; 
}


function generate_summary()
{
    global $langle_number;
    global $date;
    global $url_to_langle;
    $game_number = $langle_number; 
    $game_date = $date; 

    $guesses = $_SESSION['guesses'];
    $summary = "Langle #$game_number - $game_date\n";
    $summary .= str_pad("Guess", 10) . " | " . str_pad("Family", 6) . " | " . str_pad("Continent", 8) . " | Correct\n";
    $summary .= str_repeat("-", 60) . "\n";

    foreach ($guesses as $guess) {
        $is_correct = $guess['guess'] === $_SESSION['correct_language'] ? '✅' : '❌';
        $summary .= sprintf(
            "%-10s | %-7s | %-9s | %s\n",
            $guess['guess'],
            $guess['family_feedback'] === 'correct' ? '✅' : '❌',
            $guess['continent_feedback'] === 'correct' ? '✅' : '❌',
            $is_correct
        );
    }
    $summary .= "\nTotal Guesses: " . count($guesses);
    $summary .= "\nBonus Quiz 1 (Meaning):  " . ($_SESSION['quiz_feedback']['translation'] ? '✅' : '❌') . "\n";
    $summary .= "Bonus Quiz 2 (Country):  " . ($_SESSION['quiz_feedback']['country'] ? '✅' : '❌') . "\n";
    $summary .= "Bonus Quiz 3 (Speakers): " . ($_SESSION['quiz_feedback']['speakers'] ? '✅' : '❌') . "\n";

    $summary .= "\nThink you'd do better? Try @ " . $url_to_langle . "\n";

    return nl2br(htmlspecialchars($summary));
}
function correct_quiz_results()
{
    global $data;
    if ($_SESSION['quiz_feedback']['translation'] == "🎉 Correct! The translation was: '" . $data['translation'] . "'.")
    {
        $_SESSION['quiz_feedback']['translation'] = True;
    }
    else 
    {
        $_SESSION['quiz_feedback']['translation'] = False;
    }
    if ($_SESSION['quiz_feedback']['country'] == "🎉 Correct! The country of origin is: '" . $data['country'] . "'.")
    {
        $_SESSION['quiz_feedback']['country'] = True;
    }
    else
    {
        $_SESSION['quiz_feedback']['country'] = False;
    }
    if ($_SESSION['quiz_feedback']['speakers'] == "🎉 Correct! The number of speakers is: '" . $data['spoken_by'] . "'.")
    {
        $_SESSION['quiz_feedback']['speakers'] = True;
    }
    else
    {
        $_SESSION['quiz_feedback']['speakers'] = False;
    }
}
if (
    $_SESSION['quiz_progress']['translation'] &&
    $_SESSION['quiz_progress']['country'] &&
    $_SESSION['quiz_progress']['speakers'] && 
    $_SESSION['quiz_progress']['done'] == true
) {
    correct_quiz_results();
    $summary = generate_summary();
}
if ($_SESSION['correct']) {
    $correctLanguageMessage = "🎉 Correct! The language is " . $_SESSION['correct_language'] . ".";
    $message = $correctLanguageMessage;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    if ($url_to_langle == 'https://peter.rs/projects/langle') {
        echo '<link rel="apple-touch-icon" sizes="180x180" href="https://peter.rs/apple-touch-icon.png">';
        echo '<link rel="icon" type="image/png" sizes="32x32" href="https://peter.rs/favicon-32x32.png">';
        echo '<link rel="icon" type="image/png" sizes="16x16" href="https://peter.rs/favicon-16x16.png">';
        echo '<link rel="manifest" href="https://peter.rs/site.webmanifest">';
        echo '<link rel="mask-icon" href="https://peter.rs/safari-pinned-tab.svg" color="#5bbad5">';
        echo '<meta name="msapplication-TileColor" content="#da532c">';
    }
    else
    {
        // enter your own favicon links here if needed
    }
    ?>
    <title><?php echo $title;?></title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
        .game-container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .phrase-image { margin: 20px 0; }
        .feedback { margin: 20px 0; font-size: 1.2em; color: #333; }
        .guess-table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        .guess-table th, .guess-table td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        .guess-table th { background-color: #f4f4f4; }
        .correct { background-color: #d4edda; color: #155724; }
        .incorrect { background-color: #f8d7da; color: #721c24; }
        .quiz-feedback { color: green; margin-top: 10px; }
        .summary {
            font-size: 1em; 
            margin-top: 5px;
            margin-left: 1%;
            margin-right: 1%;
            margin-bottom: 5px;
            text-align: left;
            line-height: 1.2; 
            white-space: pre; 
            font-family: monospace; 
            border: 2px solid black; 
            border-radius: 10px; 
            padding: 10px; 
            max-width: 100%; 
            background-color: white; 
            overflow-x: auto; 
            word-wrap: break-word; 
            position: relative;
        }
        .share-button-container {
            text-align: left; 
            margin-top: 10px; 
            padding: 0; 
            display: flex; 
            justify-content: flex-start; 
        }
        .correct-feedback {
            color: #155724; 
            background-color: #d4edda; 
            padding: 5px;
            border-radius: 5px;
        }

        .incorrect-feedback {
            color: #721c24; 
            background-color: #f8d7da; 
            padding: 5px;
            border-radius: 5px;
        }
        .preclass {
            margin-top: 0px;
            margin-bottom: 0px;
        }
    </style>
</head>
<body>
    <script>
        window.onload = function() {
            window.scrollTo(0, document.body.scrollHeight);
        };
    </script>
    <div class="game-container">
        <h1><?php echo $title; ?></h1>
        <p>Can you guess the language of the phrase below?</p>
        <div class="phrase-image">
            <img src="<?= htmlspecialchars($data['image_file']) ?>" alt="Phrase" style="max-width: 100%;">
        </div>

        <?php if (isset($summary)): ?>
            <div class="summary">
                <pre class="preclass"><?= $summary ?></pre>
                <div class="share-button-container">
                    <button id="shareButton" class="share-button">📤 Share Summary</button>
                </div>
                <script>
                    document.getElementById('shareButton').addEventListener('click', async function () {
                        const summary = `<?php echo generate_sharing_summary(); ?>`;

                        if (navigator.share) {
                            try {
                                await navigator.share({
                                    title: 'Langle Summary',
                                    text: summary,
                                });
                                console.log('Summary shared successfully!');
                            } catch (error) {
                                console.error('Error sharing:', error);
                            }
                        } else {
                            alert('Sharing is not supported in your browser. Copy and paste the summary manually.');
                        }
                    });
                </script>
        <?php else: ?>
            <?php if (!$_SESSION['correct'] && $_SESSION['attempts'] < 5): ?>
                <form method="POST">
                    <input list="languages" name="guess" placeholder="Type or select a language" required>
                    <datalist id="languages">
                        <?php foreach ($language_options as $language): ?>
                            <option value="<?= htmlspecialchars($language) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <button type="submit">Submit</button>
                </form>
            <?php endif; ?>
        <div class="feedback"><?= htmlspecialchars($message) ?></div>
        <?php if (!empty($_SESSION['guesses'])): ?>
            <table class="guess-table">
                <thead>
                    <tr>
                        <th>Guess</th>
                        <th>Language Family</th>
                        <th>Continent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_SESSION['guesses'] as $guess): ?>
                        <tr>
                            <td><?= htmlspecialchars($guess['guess']) ?></td>
                            <td class="<?= $guess['family_feedback'] === 'correct' ? 'correct' : 'incorrect' ?>">
                                <?= htmlspecialchars($guess['family']) ?>
                            </td>
                            <td class="<?= $guess['continent_feedback'] === 'correct' ? 'correct' : 'incorrect' ?>">
                                <?= htmlspecialchars($guess['continent']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php if ($_SESSION['quiz_progress']['translation']): ?>
            <form method="POST">
                <h2>What does the phrase mean?</h2>
                <?php foreach ($_SESSION['quiz_translation_options'] as $option): ?>
                    <label>
                        <input type="radio" name="quiz_answer" value="<?= htmlspecialchars($option) ?>" <?= isset($_SESSION['quiz_feedback']['translation']) ? 'disabled' : '' ?>>
                        <?= htmlspecialchars($option) ?>
                    </label><br>
                <?php endforeach; ?>
                <input type="hidden" name="quiz_type" value="translation">
                <?php if (!isset($_SESSION['quiz_feedback']['translation'])): ?>
                    <button type="submit">Submit</button>
                <?php endif; ?>
            </form>
            <?php if (isset($_SESSION['quiz_feedback']['translation'])): ?>
                <?php
                $quizFeedbackClass = (strpos($_SESSION['quiz_feedback']['translation'], 'Correct!') !== false) 
                    ? 'correct-feedback' 
                    : 'incorrect-feedback';
                ?>
                <div class="quiz-feedback <?= $quizFeedbackClass ?>"><?= $_SESSION['quiz_feedback']['translation'] ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($_SESSION['quiz_progress']['country']): ?>
            <form method="POST">
                <h2>Which country is this language primarily spoken in?</h2>
                <?php foreach ($_SESSION['quiz_country_options'] as $option): ?>
                    <label>
                        <input type="radio" name="quiz_answer" value="<?= htmlspecialchars($option) ?>" <?= isset($_SESSION['quiz_feedback']['country']) ? 'disabled' : '' ?>>
                        <?= htmlspecialchars($option) ?>
                    </label><br>
                <?php endforeach; ?>
                <input type="hidden" name="quiz_type" value="country">
                <?php if (!isset($_SESSION['quiz_feedback']['country'])): ?>
                    <button type="submit">Submit</button>
                <?php endif; ?>
            </form>
            <?php if (isset($_SESSION['quiz_feedback']['country'])): ?>
                <?php
                $quizFeedbackClass = (strpos($_SESSION['quiz_feedback']['country'], 'Correct!') !== false) 
                    ? 'correct-feedback' 
                    : 'incorrect-feedback';
                ?>
                <div class="quiz-feedback <?= $quizFeedbackClass ?>"><?= $_SESSION['quiz_feedback']['country'] ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($_SESSION['quiz_progress']['speakers']): ?>
            <form method="POST">
                <h2>How many people speak this language?</h2>
                <?php foreach ($_SESSION['quiz_speaker_options'] as $option): ?>
                    <label>
                        <input type="radio" name="quiz_answer" value="<?= htmlspecialchars($option) ?>" <?= isset($_SESSION['quiz_feedback']['speakers']) ? 'disabled' : '' ?>>
                        <?= htmlspecialchars($option) ?>
                    </label><br>
                <?php endforeach; ?>
                <input type="hidden" name="quiz_type" value="speakers">
                <?php if (!isset($_SESSION['quiz_feedback']['speakers'])): ?>
                    <button type="submit">Submit</button>
                <?php endif; ?>
            </form>
            <?php if (isset($_SESSION['quiz_feedback']['speakers'])): ?>
                <?php
                $quizFeedbackClass = (strpos($_SESSION['quiz_feedback']['speakers'], 'Correct!') !== false) 
                    ? 'correct-feedback' 
                    : 'incorrect-feedback';
                ?>
                <div class="quiz-feedback <?= $quizFeedbackClass ?>"><?= $_SESSION['quiz_feedback']['speakers'] ?></div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</body>
</html>