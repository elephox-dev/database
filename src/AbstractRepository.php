<?php
declare(strict_types=1);

namespace Elephox\Database;

use Elephox\Collection\ArrayList;
use Elephox\DI\Contract\Container;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;

/**
 * @template T of Contract\Entity
 *
 * @template-implements Contract\Repository<T>
 */
abstract class AbstractRepository implements Contract\Repository
{
	/**
	 * @param class-string $className
	 * @return non-empty-string
	 */
	public static function getTableNameFromClassName(string $className): string
	{
		$lastNamespaceSeparator = strrpos($className, '\\');
		if (!$lastNamespaceSeparator) {
			throw new InvalidArgumentException('Class name must be fully qualified');
		}

		/** @var non-empty-string */
		return lcfirst(substr($className, $lastNamespaceSeparator + 1));
	}

	/** @var non-empty-string $tableName */
	private string $tableName;

	/**
	 * @param class-string<T> $entityClass
	 * @param Contract\Storage $storage
	 * @param Container $container
	 * @param non-empty-string|null $tableName
	 */
	public function __construct(
		private string           $entityClass,
		private Contract\Storage $storage,
		private Container        $container,
		?string                  $tableName = null
	)
	{
		if ($tableName === null) {
			$tableName = self::getTableNameFromClassName($entityClass);
		}

		$this->tableName = $tableName;
	}

	/**
	 * @return class-string<T>
	 */
	#[Pure] public function getEntityClass(): string
	{
		return $this->entityClass;
	}

	/**
	 * @return non-empty-string
	 */
	#[Pure] public function getTableName(): string
	{
		return $this->tableName;
	}

	/**
	 * @param null|callable(T): bool $filter
	 * @return T|null
	 */
	#[Pure] public function first(?callable $filter = null): mixed
	{
		return $this->findAll()->first($filter);
	}

	/**
	 * @param null|callable(T): bool $filter
	 * @return bool
	 */
	#[Pure] public function any(?callable $filter = null): bool
	{
		return $this->findAll()->any($filter);
	}

	/**
	 * @param callable(T): bool $filter
	 * @return ArrayList<T>
	 */
	#[Pure] public function where(callable $filter): ArrayList
	{
		return $this->findAll()->where($filter);
	}

	/**
	 * @param T $value
	 * @return bool
	 */
	#[Pure] public function contains(mixed $value): bool
	{
		return $this->findAll()->contains($value);
	}

	#[Pure] public function find(int|string $id): ?Contract\Entity
	{
		return $this->first(static fn(Contract\Entity $entity) => $entity->getUniqueId() === $id);
	}

	#[Pure] public function findBy(string $property, mixed $value): ?Contract\Entity
	{
		return $this->first(static fn(Contract\Entity $entity) => $entity->{'get' . ucfirst($property)}() === $value);
	}

	/**
	 * @return ArrayList<T>
	 */
	#[Pure] public function findAll(): ArrayList
	{
		/** @psalm-suppress ImpureMethodCall */
		return ArrayList::fromArray($this->storage->all($this->tableName))
			->map(function (array $entity): Contract\Entity {
				return $this->container->restore($this->getEntityClass(), $entity);
			});
	}

	public function add(Contract\Entity $entity): void
	{
		/** @noinspection PhpConditionAlreadyCheckedInspection */
		if (!$entity instanceof ProxyEntity) {
			$entity = new ProxyEntity($entity);
		}
		/** @var ProxyEntity $entity */

		$this->storage->add($this->tableName, $entity->_proxyGetArrayCopy());
	}

	public function update(Contract\Entity $entity): void
	{
		/** @noinspection PhpConditionAlreadyCheckedInspection */
		if (!$entity instanceof ProxyEntity) {
			$entity = new ProxyEntity($entity, true);
		}
		/** @var ProxyEntity $entity */

		if (!$entity->_proxyIsDirty()) {
			return;
		}

		$this->storage->set($this->tableName, (string)$entity->getUniqueId(), $entity->_proxyGetArrayCopy());

		$entity->_proxyResetDirty();
	}

	public function delete(Contract\Entity $entity): void
	{
		$this->storage->delete($this->tableName, (string)$entity->getUniqueId());
	}
}
