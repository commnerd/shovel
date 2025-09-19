<?php

namespace App\Services\AI\Contracts;

class AIResponse
{
    public function __construct(
        public readonly string $content,
        public readonly array $metadata = [],
        public readonly ?string $model = null,
        public readonly ?int $tokensUsed = null,
        public readonly ?float $cost = null,
        public readonly ?float $responseTime = null,
    ) {}

    /**
     * Get the response content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get response metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the model used for this response.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get the number of tokens used.
     */
    public function getTokensUsed(): ?int
    {
        return $this->tokensUsed;
    }

    /**
     * Get the estimated cost of this request.
     */
    public function getCost(): ?float
    {
        return $this->cost;
    }

    /**
     * Get the response time in seconds.
     */
    public function getResponseTime(): ?float
    {
        return $this->responseTime;
    }

    /**
     * Check if the response was successful.
     */
    public function isSuccessful(): bool
    {
        return ! empty($this->content);
    }

    /**
     * Convert response to array.
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'metadata' => $this->metadata,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
            'cost' => $this->cost,
            'response_time' => $this->responseTime,
        ];
    }

    /**
     * Parse JSON content if applicable.
     */
    public function parseJson(): array
    {
        $decoded = json_decode($this->content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Response content is not valid JSON');
        }

        return $decoded;
    }
}
