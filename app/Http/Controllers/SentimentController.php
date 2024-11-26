<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SentimentController extends Controller
{
    public function analyze(Request $request)
{
    // Get the input text from the request
    $originalText = $request->input('text'); // Preserve original text for highlighting
    $inputText = strtolower($originalText); // Convert to lowercase for case-insensitive matching

    // Load the JSON dataset
    $datasetPath = storage_path('app/data/data.json');
    if (!File::exists($datasetPath)) {
        return response()->json(['error' => 'Dataset file not found!'], 404);
    }

    $data = json_decode(File::get($datasetPath), true);

    // Extract words and phrases
    $positiveWords = $data['positive_words'] ?? [];
    $negativeWords = $data['negative_words'] ?? [];
    $neutralWords = $data['neutral_words'] ?? [];
    $positivePhrases = $data['positive_phrases'] ?? [];
    $negativePhrases = $data['negative_phrases'] ?? [];
    $neutralPhrases = $data['neutral_phrases'] ?? [];

    // Initialize counters
    $positiveCount = 0;
    $negativeCount = 0;
    $neutralCount = 0;

    // Step 1: Highlight phrases first and replace them with placeholders
    $highlightedPhrases = []; // To track highlighted phrases
    foreach ($positivePhrases as $phrase) {
        if (stripos($inputText, $phrase) !== false) {
            $positiveCount++;
            $highlight = "<span class='highlight positive'>{$phrase}</span>";
            $highlightedPhrases["__PHRASE_" . count($highlightedPhrases) . "__"] = $highlight;
            $inputText = str_ireplace($phrase, "__PHRASE_" . (count($highlightedPhrases) - 1) . "__", $inputText);
        }
    }

    foreach ($negativePhrases as $phrase) {
        if (stripos($inputText, $phrase) !== false) {
            $negativeCount++;
            $highlight = "<span class='highlight negative'>{$phrase}</span>";
            $highlightedPhrases["__PHRASE_" . count($highlightedPhrases) . "__"] = $highlight;
            $inputText = str_ireplace($phrase, "__PHRASE_" . (count($highlightedPhrases) - 1) . "__", $inputText);
        }
    }

    foreach ($neutralPhrases as $phrase) {
        if (stripos($inputText, $phrase) !== false) {
            $neutralCount++;
            $highlight = "<span class='highlight neutral'>{$phrase}</span>";
            $highlightedPhrases["__PHRASE_" . count($highlightedPhrases) . "__"] = $highlight;
            $inputText = str_ireplace($phrase, "__PHRASE_" . (count($highlightedPhrases) - 1) . "__", $inputText);
        }
    }

    // Step 2: Highlight remaining single words
    $words = explode(' ', $inputText); // Split the text into words

    foreach ($words as &$word) {
        if (in_array($word, $positiveWords)) {
            $positiveCount++;
            $word = "<span class='highlight positive'>{$word}</span>";
        } elseif (in_array($word, $negativeWords)) {
            $negativeCount++;
            $word = "<span class='highlight negative'>{$word}</span>";
        } elseif (in_array($word, $neutralWords)) {
            $neutralCount++;
            $word = "<span class='highlight neutral'>{$word}</span>";
        }
    }

    // Step 3: Reassemble the text with placeholders replaced
    $highlightedText = implode(' ', $words);
    foreach ($highlightedPhrases as $placeholder => $highlightedPhrase) {
        $highlightedText = str_replace($placeholder, $highlightedPhrase, $highlightedText);
    }

    // Step 4: Calculate sentiment score
    $rawScore = ($positiveCount * 1) + ($negativeCount * -1); // Weighted raw score
    $totalWords = $positiveCount + $negativeCount + $neutralCount;

    // Normalize score to a range between -1 and 1
    $score = $totalWords > 0 ? $rawScore / $totalWords : 0;

    // Calculate magnitude (absolute sum of all counts)
    $magnitude = $totalWords > 0 ? ($positiveCount + $negativeCount + $neutralCount) / $totalWords : 0;

    // Step 5: Determine grade based on normalized score
    $grade = 'Neutral'; // Default grade
    if ($score > 0.25) {
        $grade = 'Positive';
    } elseif ($score < -0.25) {
        $grade = 'Negative';
    }

    // Step 6: Determine overall sentiment based on counts
    $overallSentiment = 'neutral';
    if ($positiveCount > $negativeCount && $positiveCount > $neutralCount) {
        $overallSentiment = 'positive';
    } elseif ($negativeCount > $positiveCount && $negativeCount > $neutralCount) {
        $overallSentiment = 'negative';
    }

    // Return the result as a JSON response
    return response()->json([
        'text' => $originalText,
        'highlighted_text' => $highlightedText, // Highlighted text to display
        'positive_count' => $positiveCount,
        'negative_count' => $negativeCount,
        'neutral_count' => $neutralCount,
        'total_word_count' => $totalWords,
        'overall_sentiment' => $grade,
        'score' => round($score, 2), // Normalized score
        'magnitude' => round($magnitude, 2), // Magnitude
        'grade' => $grade // Add grade here
    ]);
}
        
private function highlightWords($text, $positiveWords, $positiveColor, $negativeWords, $negativeColor, $neutralWords, $neutralColor, $positivePhrases, $negativePhrases, $neutralPhrases)
{
    // 1. Highlight phrases first to avoid splitting them into words.
    foreach ($positivePhrases as $phrase) {
        $text = preg_replace_callback('/\b' . preg_quote($phrase, '/') . '\b/i', function ($matches) use ($positiveColor) {
            return "<span class='highlight positive'>{$matches[0]}</span>";
        }, $text);
    }

    foreach ($negativePhrases as $phrase) {
        $text = preg_replace_callback('/\b' . preg_quote($phrase, '/') . '\b/i', function ($matches) use ($negativeColor) {
            return "<span class='highlight negative'>{$matches[0]}</span>";
        }, $text);
    }

    foreach ($neutralPhrases as $phrase) {
        $text = preg_replace_callback('/\b' . preg_quote($phrase, '/') . '\b/i', function ($matches) use ($neutralColor) {
            return "<span class='highlight neutral'>{$matches[0]}</span>";
        }, $text);
    }

    // 2. Remove matched phrases from the text temporarily to prevent word-level matches inside phrases.
    $textWithoutPhrases = strip_tags($text);

    // 3. Highlight single words (only for text that hasn’t been matched as a phrase).
    foreach ($positiveWords as $word) {
        $textWithoutPhrases = preg_replace_callback('/\b' . preg_quote($word, '/') . '\b/i', function ($matches) use ($positiveColor) {
            return "<span class='highlight positive'>{$matches[0]}</span>";
        }, $textWithoutPhrases);
    }

    foreach ($negativeWords as $word) {
        $textWithoutPhrases = preg_replace_callback('/\b' . preg_quote($word, '/') . '\b/i', function ($matches) use ($negativeColor) {
            return "<span class='highlight negative'>{$matches[0]}</span>";
        }, $textWithoutPhrases);
    }

    foreach ($neutralWords as $word) {
        $textWithoutPhrases = preg_replace_callback('/\b' . preg_quote($word, '/') . '\b/i', function ($matches) use ($neutralColor) {
            return "<span class='highlight neutral'>{$matches[0]}</span>";
        }, $textWithoutPhrases);
    }

    // 4. Merge the phrase-highlighted text with word-highlighted text.
    $finalText = $textWithoutPhrases;

    return $finalText;
}


}