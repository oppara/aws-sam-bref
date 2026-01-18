<?php

declare(strict_types=1);

namespace App\Service\Recaptcha;

use App\Config;
use Google\Cloud\RecaptchaEnterprise\V1\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\CreateAssessmentRequest;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Psr\Log\LoggerInterface;

/** @package App\Service\Recaptcha */
class RecaptchaEnterpriseService implements RecaptchaServiceInterface
{
    private RecaptchaEnterpriseServiceClient $client;

    public function __construct(
        private Config $config,
        private LoggerInterface $logger,
    ) {
        $this->client = new RecaptchaEnterpriseServiceClient();
    }

    /**
     * reCAPTCHA Enterpriseで検証
     *
     * @param string $token クライアントから送信されたreCAPTCHAトークン
     * @return array{score: float, success: bool, errors: array<string>}
     * @throws \RuntimeException 検証に失敗した場合
     */
    public function verify(string $token): array
    {
        try {
            $projectName = $this->client->projectName($this->config->recaptchaProjectId);

            $event = new Event();
            $event->setToken($token);
            $event->setExpectedAction('submit');
            $event->setSiteKey($this->config->recaptchaSiteKey);

            $assessment = new Assessment();
            $assessment->setEvent($event);

            $createAssessmentRequest = new CreateAssessmentRequest();
            $createAssessmentRequest->setParent($projectName);
            $createAssessmentRequest->setAssessment($assessment);

            $response = $this->client->createAssessment($createAssessmentRequest);

            $score = $response->getRiskAnalysis()->getScore();
            $reasons = $response->getRiskAnalysis()->getReasons();
            $isValid = $score >= $this->config->recaptchaScoreThreshold;

            $this->logger->info('reCAPTCHA Enterprise assessment completed', [
                'score' => $score,
                'reasons' => $reasons,
                'isValid' => $isValid,
            ]);

            return [
                'score' => $score,
                'success' => $isValid,
                'errors' => $reasons,
            ];
        } catch (\Exception $e) {
            $this->logger->error('reCAPTCHA Enterprise assessment failed', [
                'error' => $e->getMessage(),
            ]);

            throw new RecaptchaException('reCAPTCHA Enterprise assessment failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
