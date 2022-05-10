<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ScandiPWA\PersistedQuery\Query;

use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use Magento\Framework\GraphQl\Exception\ExceptionFormatter;
use Magento\Framework\GraphQl\Query\QueryProcessor as CoreQueryProcessor;
use Magento\Framework\GraphQl\Query\ErrorHandlerInterface;
use Magento\Framework\GraphQl\Query\Promise;
use Magento\Framework\GraphQl\Query\QueryComplexityLimiter;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema;
use Magento\Framework\App\ResponseInterface;

/**
 * Wrapper for GraphQl execution of a schema
 */
class QueryProcessor extends CoreQueryProcessor
{
    /**
     * @var ExceptionFormatter
     */
    private $exceptionFormatter;

    /**
     * @var QueryComplexityLimiter
     */
    private $queryComplexityLimiter;

    /**
     * @var ErrorHandlerInterface
     */
    private $errorHandler;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @param ExceptionFormatter $exceptionFormatter
     * @param QueryComplexityLimiter $queryComplexityLimiter
     * @param ErrorHandlerInterface $errorHandler
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        ExceptionFormatter $exceptionFormatter,
        QueryComplexityLimiter $queryComplexityLimiter,
        ErrorHandlerInterface $errorHandler,
        ResponseInterface $response
    ) {
        parent::__construct(
            $exceptionFormatter,
            $queryComplexityLimiter,
            $errorHandler
        );

        $this->exceptionFormatter = $exceptionFormatter;
        $this->queryComplexityLimiter = $queryComplexityLimiter;
        $this->errorHandler = $errorHandler;
        $this->response = $response;
    }

    /**
     * Process a GraphQl query according to defined schema
     *
     * @param Schema $schema
     * @param string $source
     * @param ContextInterface $contextValue
     * @param array|null $variableValues
     * @param string|null $operationName
     * @return Promise|array
     */
    public function process(
        Schema $schema,
        $source, // can be both string and parsed document
        ContextInterface $contextValue = null,
        array $variableValues = null,
        string $operationName = null
    ) : array {
        $this->queryComplexityLimiter->execute();

        /** @var QueryComplexity $queryComplexity */
        $queryComplexity = DocumentValidator::getRule(QueryComplexity::class);

        $rootValue = null;
        $result = GraphQL::executeQuery(
            $schema,
            $source,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName
        )->setErrorsHandler(
            [$this->errorHandler, 'handle']
        )->toArray(
            $this->exceptionFormatter->shouldShowDetail() ?
                DebugFlag::INCLUDE_DEBUG_MESSAGE : 0
        );

        $this->response->setHeader('Query-Complexity', $queryComplexity->getQueryComplexity(), true);

        return $result;
    }
}
