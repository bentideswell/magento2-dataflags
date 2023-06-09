<?php
/**
 * @
 */
declare(strict_types=1);

namespace FishPig\DataFlags\Model;

use Magento\Framework\Exception\InvalidArgumentException;

class Flag implements FlagInterface
{

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var array
     */
    private $typeMap = [];

    /**
     *
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        array $typeMap = []
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->typeMap = $typeMap;
    }

    /**
     * @param  string $flag
     * @param  BaseSalesModel $object
     * @param  ?string $return = self::FLAG
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function get(object $object, string $flag, ?string $return = 'value')
    {
        list($objectType, $objectId) = $this->getObjectData($object);

        if (null === ($value = $this->load($flag, $objectType, $objectId))) {
            return null;
        }

        if ($return === null) {
            return $value;
        } elseif (isset($value[$return])) {
            return $value[$return];
        }

        throw new InvalidArgumentException(
            __(
                "Unable to find '%1' in flag '%2' for %3=%4.",
                (string)$return,
                $flag,
                $objectType,
                $objectId
            )
        );
    }

    /**
     * @
     */
    public function set(object $object, string $flag, int $value = 1, $msg = null): void
    {
        list($objectType, $objectId) = $this->getObjectData($object);
        $flagTable = $this->getFlagTable();
        $data = ['value' => $value, 'message' => $msg];
        $db = $this->getConnection();

        $flagId = (int)$db->fetchOne(
            $db->select()
                ->from($flagTable, 'flag_id')
                ->where('flag=?', $flag)
                ->where('object_type=?', $objectType)
                ->where('object_id=?', $objectId)
                ->limit(1)
        );

        if ($flagId !== 0) {
            $db->update($flagTable, $data, 'flag_id=' . $flagId);
        } else {
            $db->insert(
                $flagTable,
                array_merge(
                    [
                        'flag' => $flag,
                        'object_type' => $objectType,
                        'object_id' => $objectId
                    ],
                    $data
                )
            );
        }

        if (!isset($this->data[$objectType])) {
            $this->data[$objectType] = [];
        }

        $this->data[$objectType][$flag] = $data;
    }

    public function joinDataFlagTableToCollection(
        \Magento\Framework\Data\Collection $collection,
        string $flag,
        string $joinType = 'join'
    ): string {
        $object = $collection->getNewEmptyItem()->setId(1);
        list($objectType, $objectId) = $this->getObjectData($object);
        $idField = $object->getResource()->getIdFieldName();

        $alias = 'dataflag_' . $flag;

        $collection->getSelect()->$joinType(
            [$alias => $this->getFlagTable()],
            $collection->getConnection()->quoteInto(
                "$alias.object_id = main_table.$idField AND {$alias}.object_type = ?",
                $objectType
            ),
            [
                $alias  . '_value' => 'value',
                $alias . '_message' => 'message'
            ]
        );

        return $alias;
    }

    /**
     *
     */
    public function delete(object $object, string $flag): void
    {
        list($objectType, $objectId) = $this->getObjectData($object);
        $db = $this->getConnection();
        $db->delete(
            $this->getFlagTable(),
            $db->quoteInto('flag=?', $flag)
            . $db->quoteInto(' AND object_type=?', $objectType)
            . $db->quoteInto(' AND object_id=?', $objectId)
        );
    }

    /**
     * @
     */
    private function load(string $flag, string $objectType, int $objectId): ?array
    {
        if (!isset($this->data[$objectType][$flag][$objectId])) {
            if (!isset($this->data[$objectType])) {
                $this->data[$objectType] = [];
            }

            if (!isset($this->data[$objectType][$flag])) {
                $this->data[$objectType][$flag] = [];
            }

            $db = $this->getConnection();
            $this->data[$objectType][$flag][$objectId] = $db->fetchRow(
                $db->select()->from(
                    $this->getFlagTable(),
                    '*'
                )->where(
                    'flag=?',
                    $flag
                )->where(
                    'object_type=?',
                    $objectType
                )->where(
                    'object_id=?',
                    $objectId
                )->limit(
                    1
                )
            ) ?: null;
        }

        return $this->data[$objectType][$flag][$objectId] ?: null;
    }

    /**
     * @param
     * @return string
     */
    private function getObjectData(object $object): array
    {
        $class = $this->getClassName($object);

        if (($objectId = (int)$object->getId()) === 0) {
            throw new InvalidArgumentException(
                __("Cannot use flags for an object (%1) that has no ID.",
                $class)
            );
        }

        if (!isset($this->typeMap[$class])) {
            foreach ($this->typeMap as $type => $className) {
                if ($object instanceof $className) {
                    $this->typeMap[$class] = $type;
                    break;
                }
            }

            if (!isset($this->typeMap[$class])) {
                throw new InvalidArgumentException(
                    __("Unable to find type for class '%1'.", $class)
                );
            }
        }

        return [$this->typeMap[$class], $objectId];
    }

    //
    private function getClassName(object $object): string
    {
        $class = get_class($object);
        $class = preg_replace('/\\\(Interceptor)$/', '', $class);
        return $class;
    }

    /**
     * @param  string|object $object
     * @return string
     * @throws InvalidArgumentException
     */
    private function getFlagTable()
    {
        return $this->resourceConnection->getTableName('fishpig_dataflag');
    }

    /**
     *
     */
    private function getConnection()
    {
        return $this->resourceConnection->getConnection('core_write');
    }
}