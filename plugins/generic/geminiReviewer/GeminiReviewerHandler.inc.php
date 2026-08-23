<?php
import('classes.handler.Handler');

class GeminiReviewerHandler extends Handler {
    
    private function getEnvVariable($key, $default = null) {
        $envPath = Core::getBaseDir() . DIRECTORY_SEPARATOR . '.env';
        if (!file_exists($envPath) || !is_readable($envPath)) {
            $envPath = dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . '.env';
        }

        if (file_exists($envPath) && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($envKey, $envVal) = explode('=', $line, 2);
                    if (trim($envKey) === $key) {
                        return trim($envVal, " \t\n\r\0\x0B\"'");
                    }
                }
            }
        }

        $sysEnv = getenv($key);
        return ($sysEnv !== false) ? $sysEnv : $default;
    }

    public function generateReview($args, $request) {
        header('Content-Type: application/json');

        $apiKey = $this->getEnvVariable('GEMINI_API_KEY');
        $model  = $this->getEnvVariable('GEMINI_MODEL', 'gemini-3.6-flash');

        if (empty($apiKey)) {
            echo json_encode(array(
                'status' => false, 
                'message' => 'GEMINI_API_KEY tidak ditemukan di file .env root OJS.'
            ));
            exit;
        }

        $context = $request->getContext();
        $submissionId = (int) $request->getUserVar('submissionId');
        
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($submissionId, $context ? $context->getId() : null);

        if (!$submission) {
            echo json_encode(array('status' => false, 'message' => 'Data naskah tidak ditemukan.'));
            exit;
        }

        $title = (string) $submission->getLocalizedTitle();
        $abstract = (string) strip_tags($submission->getLocalizedAbstract());

        $systemInstruction = "You are an expert double-blind academic peer reviewer for a reputable scientific journal. Evaluate the manuscript thoroughly based on: 1) Contribution & Novelty, 2) Methodological Soundness, 3) Logical Flow & Organization, 4) Critical Flaws & Weaknesses, 5) Concrete Suggestions for Improvement, and 6) Verdict Recommendation (Accept, Minor Revisions, Major Revisions, or Decline).";
        $promptText = "Please review the following manuscript submission:\n\nTITLE:\n" . $title . "\n\nABSTRACT:\n" . $abstract . "\n";

        $postData = array(
            'contents' => array(
                array(
                    'role' => 'user',
                    'parts' => array(
                        array('text' => $systemInstruction . "\n\n" . $promptText)
                    )
                )
            )
        );

        $cleanModel = str_replace('models/', '', $model);
        $genUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $cleanModel . ':generateContent?key=' . $apiKey;

        $chGen = curl_init($genUrl);
        curl_setopt($chGen, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chGen, CURLOPT_POST, true);
        curl_setopt($chGen, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($chGen, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($chGen, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($chGen, CURLOPT_TIMEOUT, 60);
        $response = curl_exec($chGen);
        $httpCode = curl_getinfo($chGen, CURLINFO_HTTP_CODE);
        curl_close($chGen);

        if ($httpCode !== 200) {
            echo json_encode(array(
                'status' => false, 
                'message' => 'Gemini API Error (HTTP ' . $httpCode . '): ' . $response
            ));
            exit;
        }

        $resDecoded = json_decode($response, true);
        $outputText = 'Tidak ada ulasan.';
        if (isset($resDecoded['candidates'][0]['content']['parts'][0]['text'])) {
            $outputText = $resDecoded['candidates'][0]['content']['parts'][0]['text'];
        }

        echo json_encode(array(
            'status' => true,
            'submissionId' => $submissionId,
            'review' => $outputText
        ));
        exit;
    }
}
