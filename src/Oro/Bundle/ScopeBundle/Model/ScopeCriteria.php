<?php

namespace Oro\Bundle\ScopeBundle\Model;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Oro\Component\DoctrineUtils\ORM\QueryBuilderUtil;

/**
 * Contains a set of parameters to filter Scope entities
 * and provides methods to apply these parameters to scope related parts of ORM query.
 */
class ScopeCriteria implements \IteratorAggregate
{
    public const IS_NOT_NULL = 'IS_NOT_NULL';

    /** @var array */
    private $context;

    /** @var ClassMetadata */
    private $scopeClassMetadata;

    /**
     * @param array         $context            [parameter name => parameter value, ...]
     * @param ClassMetadata $scopeClassMetadata The metadata of Scope entity
     */
    public function __construct(array $context, ClassMetadata $scopeClassMetadata)
    {
        $this->context = $context;
        $this->scopeClassMetadata = $scopeClassMetadata;
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $alias
     * @param string[]     $ignoreFields
     */
    public function applyWhere(QueryBuilder $qb, string $alias, array $ignoreFields = []): void
    {
        $this->doApplyWhere($qb, $alias, $ignoreFields, false);
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $alias
     * @param string[]     $ignoreFields
     */
    public function applyWhereWithPriority(QueryBuilder $qb, string $alias, array $ignoreFields = []): void
    {
        $this->doApplyWhere($qb, $alias, $ignoreFields, true);
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $alias
     * @param string[]     $ignoreFields
     */
    public function applyToJoin(QueryBuilder $qb, string $alias, array $ignoreFields = []): void
    {
        /** @var Join[] $joins */
        $joins = $qb->getDQLPart('join');
        $qb->resetDQLPart('join');
        $this->reapplyJoins($qb, $joins, $alias, $ignoreFields, false);
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $alias
     * @param string[]     $ignoreFields
     */
    public function applyToJoinWithPriority(QueryBuilder $qb, string $alias, array $ignoreFields = []): void
    {
        /** @var Join[] $joins */
        $joins = $qb->getDQLPart('join');
        $qb->resetDQLPart('join');
        $this->reapplyJoins($qb, $joins, $alias, $ignoreFields, true);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->context);
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $alias
     * @param string[]     $ignoreFields
     * @param bool         $withPriority
     */
    private function doApplyWhere(
        QueryBuilder $qb,
        string $alias,
        array $ignoreFields,
        bool $withPriority
    ): void {
        QueryBuilderUtil::checkIdentifier($alias);
        foreach ($this->context as $field => $value) {
            QueryBuilderUtil::checkIdentifier($field);
            if (in_array($field, $ignoreFields, true)) {
                continue;
            }
            $condition = null;
            if ($this->isCollectionValuedAssociation($field)) {
                $localAlias = $alias . '_' . $field;
                $condition = $this->resolveBasicCondition($qb, $localAlias, 'id', $value, $withPriority);
                $qb->leftJoin($alias . '.' . $field, $localAlias, Join::WITH, $condition);
            } else {
                $condition = $this->resolveBasicCondition($qb, $alias, $field, $value, $withPriority);
            }
            $qb->andWhere($condition);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param Join[]       $joins
     * @param string       $alias
     * @param string[]     $ignoreFields
     * @param bool         $withPriority
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function reapplyJoins(
        QueryBuilder $qb,
        array $joins,
        string $alias,
        array $ignoreFields,
        bool $withPriority
    ): void {
        QueryBuilderUtil::checkIdentifier($alias);
        foreach ($joins as $join) {
            if (is_array($join)) {
                $this->reapplyJoins($qb, $join, $alias, $ignoreFields, $withPriority);
                continue;
            }

            $parts = [];
            $additionalJoins = [];
            $joinCondition = $join->getCondition();
            if ($joinCondition) {
                $parts[] = $joinCondition;
            }
            if ($join->getAlias() === $alias) {
                $usedFields = [];
                if ($joinCondition) {
                    $usedFields = $this->getUsedFields($joinCondition, $alias);
                }
                foreach ($this->context as $field => $value) {
                    if (in_array($field, $ignoreFields, true) || in_array($field, $usedFields, true)) {
                        continue;
                    }
                    if ($this->isCollectionValuedAssociation($field)) {
                        $additionalJoins[$field] = $this->resolveBasicCondition(
                            $qb,
                            $alias . '_' . $field,
                            'id',
                            $value,
                            $withPriority
                        );
                    } else {
                        $parts[] = $this->resolveBasicCondition($qb, $alias, $field, $value, $withPriority);
                    }
                }
            }

            $condition = $this->getConditionFromParts($parts, $withPriority);
            $this->applyJoinWithModifiedCondition($qb, $condition, $join);
            if (!empty($additionalJoins)) {
                $additionalJoins = array_filter($additionalJoins);
                foreach ($additionalJoins as $field => $condition) {
                    QueryBuilderUtil::checkIdentifier($field);
                    $qb->leftJoin($alias . '.' . $field, $alias . '_' . $field, Join::WITH, $condition);
                    if (!$withPriority) {
                        $qb->andWhere($condition);
                    }
                }
            }
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $alias
     * @param string       $field
     * @param mixed        $value
     * @param bool         $withPriority
     *
     * @return mixed
     */
    private function resolveBasicCondition(
        QueryBuilder $qb,
        string $alias,
        string $field,
        $value,
        bool $withPriority
    ) {
        QueryBuilderUtil::checkIdentifier($alias);
        QueryBuilderUtil::checkIdentifier($field);

        $aliasedField = $alias . '.' . $field;
        if ($value === null) {
            $part = $qb->expr()->isNull($aliasedField);
        } elseif ($value === self::IS_NOT_NULL) {
            $part = $qb->expr()->isNotNull($aliasedField);
        } else {
            $paramName = $alias . '_param_' . $field;
            if (is_array($value)) {
                $comparisonCondition = $qb->expr()->in($aliasedField, ':' . $paramName);
            } else {
                $comparisonCondition = $qb->expr()->eq($aliasedField, ':' . $paramName);
            }
            if ($withPriority) {
                $part = $qb->expr()->orX(
                    $comparisonCondition,
                    $qb->expr()->isNull($aliasedField)
                );
            } else {
                $part = $comparisonCondition;
            }
            $qb->setParameter($paramName, $value);
            if ($withPriority) {
                $qb->addOrderBy($aliasedField, Criteria::DESC);
            }
        }

        return $part;
    }

    /**
     * @param array $parts
     * @param bool  $withPriority
     *
     * @return string
     */
    private function getConditionFromParts(array $parts, bool $withPriority): string
    {
        if ($withPriority) {
            $parts = array_map(
                function ($part) {
                    return '(' . $part . ')';
                },
                $parts
            );
        }

        return implode(' AND ', $parts);
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $condition
     * @param Join         $join
     */
    private function applyJoinWithModifiedCondition(QueryBuilder $qb, string $condition, Join $join): void
    {
        if (Join::INNER_JOIN === $join->getJoinType()) {
            $qb->innerJoin(
                $join->getJoin(),
                $join->getAlias(),
                $join->getConditionType(),
                $condition,
                $join->getIndexBy()
            );
        }
        if (Join::LEFT_JOIN === $join->getJoinType()) {
            $qb->leftJoin(
                $join->getJoin(),
                $join->getAlias(),
                $join->getConditionType(),
                $condition,
                $join->getIndexBy()
            );
        }
    }

    /**
     * @param string $condition
     * @param string $alias
     *
     * @return string[]
     */
    private function getUsedFields(string $condition, string $alias): array
    {
        $fields = [];
        $parts = explode(' AND ', $condition);
        foreach ($parts as $part) {
            $matches = [];
            preg_match(sprintf('/%s\.\w+/', $alias), $part, $matches);
            foreach ($matches as $match) {
                $fields[] = explode('.', $match)[1];
            }
        }

        return $fields;
    }

    /**
     * @param string $field
     *
     * @return bool
     */
    private function isCollectionValuedAssociation(string $field): bool
    {
        if (!$this->scopeClassMetadata->hasAssociation($field)) {
            return false;
        }

        return $this->scopeClassMetadata->isCollectionValuedAssociation($field);
    }
}
