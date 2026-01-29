<?php

namespace Qc\QcRedirects\Controller\ExtendedRedirectModule;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Redirects\Repository\Demand;
class DemandExt extends Demand
{
    protected const ORDER_FIELDS = ['title', 'source_host', 'source_path', 'lasthiton', 'hitcount', 'protected', 'createdon'];
    protected string $title;


    public function __construct(
        int $page = 1,
        string $orderField = self::DEFAULT_ORDER_FIELD,
        string $orderDirection = self::ORDER_ASCENDING,
        array $sourceHosts = [],
        string $sourcePath = '',
        string $target = '',
        array $statusCodes = [],
        int $maxHits = 0,
        ?\DateTimeInterface $olderThan = null,
        ?int $creationType = -1,
        string $title = ''
    ) {
        parent::__construct(
            $page,$orderField,$orderDirection,$sourceHosts,$sourcePath,$target,
            $statusCodes,
            $maxHits,
            $olderThan,
            $creationType
        );
        if (!in_array($orderField, self::ORDER_FIELDS, true)) {
            $orderField = self::DEFAULT_ORDER_FIELD;
        }
        $this->orderField = $orderField;
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return bool
     */
    public function hasTitle(): bool
    {
        return $this->title !== '';
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $page = (int)($request->getQueryParams()['page'] ?? $request->getParsedBody()['page'] ?? 1);
        $orderField = $request->getQueryParams()['orderField'] ?? $request->getParsedBody()['orderField'] ?? self::DEFAULT_ORDER_FIELD;
        $orderDirection = $request->getQueryParams()['orderDirection'] ?? $request->getParsedBody()['orderDirection'] ?? self::ORDER_ASCENDING;
        $demand = $request->getQueryParams()['demand'] ?? $request->getParsedBody()['demand'] ?? [];
        if (empty($demand)) {
            return new self($page, $orderField, $orderDirection);
        }
        $sourceHost = $demand['source_host'] ?? '';
        $sourceHosts = $sourceHost ? [$sourceHost] : [];
        $sourcePath = $demand['source_path'] ?? '';
        $statusCode = (int)($demand['target_statuscode'] ?? 0);
        $statusCodes = $statusCode > 0 ? [$statusCode] : [];
        $target = $demand['target'] ?? '';
        $maxHits = (int)($demand['max_hits'] ?? 0);
        $creationType = isset($demand['creation_type']) ? ((int)$demand['creation_type']) : -1;
        $title = $demand['title'] ?? '';
        return new self($page, $orderField, $orderDirection, $sourceHosts, $sourcePath, $target, $statusCodes, $maxHits, null, $creationType, $title);
    }

    /**
     * @return bool
     */
    public function hasConstraints(): bool
    {
        return parent::hasConstraints() || $this->hasTitle();
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        $parameters = parent::getParameters();
        if ($this->hasTitle()) {
            $parameters['title'] = $this->getTitle();
        }
        return $parameters;
    }
}
