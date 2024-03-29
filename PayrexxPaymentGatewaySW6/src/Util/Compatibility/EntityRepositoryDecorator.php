<?php declare(strict_types=1);

namespace PayrexxPaymentGateway\Util\Compatibility;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;

/**
 * @internal
 *
 * required until min version 6.5
 */
if (\interface_exists(EntityRepositoryInterface::class)) {
    // @phpstan-ignore-next-line EntityRepository is final, but we only extend it in 6.4, so we are fine
    class EntityRepositoryDecorator extends EntityRepository implements EntityRepositoryInterface
    {
        private EntityRepositoryInterface $inner;

        public function __construct(EntityRepositoryInterface $inner)
        {
            $this->inner = $inner;
        }

        public function getDefinition(): EntityDefinition
        {
            return $this->inner->getDefinition();
        }

        public function search(Criteria $criteria, Context $context): EntitySearchResult
        {
            return $this->inner->search($criteria, $context);
        }

        public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
        {
            return $this->inner->aggregate($criteria, $context);
        }

        public function searchIds(Criteria $criteria, Context $context): IdSearchResult
        {
            return $this->inner->searchIds($criteria, $context);
        }

        public function update(array $data, Context $context): EntityWrittenContainerEvent
        {
            return $this->inner->update($data, $context);
        }

        public function upsert(array $data, Context $context): EntityWrittenContainerEvent
        {
            return $this->inner->upsert($data, $context);
        }

        public function create(array $data, Context $context): EntityWrittenContainerEvent
        {
            return $this->inner->create($data, $context);
        }

        public function delete(array $ids, Context $context): EntityWrittenContainerEvent
        {
            return $this->inner->delete($ids, $context);
        }

        public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
        {
            return $this->inner->createVersion($id, $context, $name, $versionId);
        }

        public function merge(string $versionId, Context $context): void
        {
            $this->inner->merge($versionId, $context);
        }

        public function clone(string $id, Context $context, ?string $newId = null, ?CloneBehavior $behavior = null): EntityWrittenContainerEvent
        {
            return $this->inner->clone($id, $context, $newId, $behavior);
        }
    }
}
