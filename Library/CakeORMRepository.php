<?php

namespace CakeOrm\Library;

use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Rad\Authentication\AbstractRepository;
use Rad\Authentication\Exception\CredentialInvalidException;
use Rad\Authentication\Exception\IdentityNotFoundException;

/**
 * CakeORM Authentication Repository
 *
 * @package CakeOrm\Library
 */
class CakeORMRepository extends AbstractRepository
{
    /**
     * The alias for users table, defaults to "Users.Users".
     *
     * @var string
     */
    protected $tableAlias = 'Users.Users';

    /**
     * Identity column name
     *
     * @var string
     */
    protected $identityColumn = 'username';

    /**
     * Credential column name
     *
     * @var string
     */
    protected $credentialColumn = 'password';

    /**
     * Extra models to contain and store in session.
     *
     * @var string|null
     */
    protected $contain;

    /**
     * Additional conditions to use when looking up and authenticating users,
     *    i.e. `['Users.status' => 1].`
     *
     * @var array
     */
    protected $scope = [];

    /**
     * CakeOrm\Library\CakeORMRepository constructor
     *
     * @param string $tableAlias
     * @param string $identityColumn
     * @param string $credentialColumn
     */
    public function __construct(
        $tableAlias = 'Users.Users',
        $identityColumn = 'username',
        $credentialColumn = 'password'
    ) {
        parent::__construct();

        $this->tableAlias = $tableAlias;
        $this->identityColumn = $identityColumn;
        $this->credentialColumn = $credentialColumn;
    }

    /**
     * Factory method for chain ability.
     *
     * @param string $tableAlias
     * @param string $identityColumn
     * @param string $credentialColumn
     *
     * @return CakeORMRepository
     */
    public static function create(
        $tableAlias = 'Users.Users',
        $identityColumn = 'username',
        $credentialColumn = 'password'
    ) {
        return new static($tableAlias, $identityColumn, $credentialColumn);
    }

    /**
     * {@inheritdoc}
     */
    public function findUser($identity, $credential)
    {
        /** @var Entity $entity */
        $entity = $this->getQuery($identity)->first();

        if (null === $entity) {
            throw new IdentityNotFoundException();
        }

        if (false === $this->passwordCrypt->verify($credential, $entity->get($this->credentialColumn))) {
            throw new CredentialInvalidException();
        }

        $entity->unsetProperty($this->credentialColumn);

        return $entity->toArray();
    }

    /**
     * Get query object for fetching user from database.
     *
     * @param string $identity The username/identifier.
     *
     * @return \Cake\ORM\Query
     */
    protected function getQuery($identity)
    {
        $table = TableRegistry::get($this->tableAlias);
        $conditions = [$table->aliasField($this->identityColumn) => $identity];
        if (!empty($this->scope)) {
            $conditions = array_merge($conditions, $this->scope);
        }

        $query = $table->find('all')
            ->where($conditions);

        if (!empty($this->contain)) {
            $query = $query->contain($this->contain);
        }

        return $query;
    }

    /**
     * @param string $tableAlias
     *
     * @return CakeORMRepository
     */
    public function setTableAlias($tableAlias)
    {
        $this->tableAlias = $tableAlias;

        return $this;
    }

    /**
     * @param string $identityColumn
     *
     * @return CakeORMRepository
     */
    public function setIdentityColumn($identityColumn)
    {
        $this->identityColumn = $identityColumn;

        return $this;
    }

    /**
     * @param string $credentialColumn
     *
     * @return CakeORMRepository
     */
    public function setCredentialColumn($credentialColumn)
    {
        $this->credentialColumn = $credentialColumn;

        return $this;
    }

    /**
     * @param null|string $contain
     *
     * @return CakeORMRepository
     */
    public function setContain($contain)
    {
        $this->contain = $contain;

        return $this;
    }

    /**
     * @param array $scope
     *
     * @return CakeORMRepository
     */
    public function setScope(array $scope)
    {
        $this->scope = $scope;

        return $this;
    }
}
