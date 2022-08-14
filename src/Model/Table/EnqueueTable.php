<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Enqueue\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Enqueue Model
 *
 * @property \App\Model\Table\DeliveriesTable&\Cake\ORM\Association\BelongsTo $Deliveries
 * @method \App\Model\Entity\Enqueue newEmptyEntity()
 * @method \App\Model\Entity\Enqueue newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Enqueue[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Enqueue get($primaryKey, $options = [])
 * @method \App\Model\Entity\Enqueue findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Enqueue patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Enqueue[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Enqueue|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Enqueue saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Enqueue[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Enqueue[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Enqueue[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Enqueue[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class EnqueueTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('enqueue');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('published_at')
            ->allowEmptyString('published_at');

        $validator
            ->scalar('body')
            ->allowEmptyString('body');

        $validator
            ->scalar('headers')
            ->allowEmptyString('headers');

        $validator
            ->scalar('properties')
            ->allowEmptyString('properties');

        $validator
            ->boolean('redelivered')
            ->allowEmptyString('redelivered');

        $validator
            ->scalar('queue')
            ->maxLength('queue', 255)
            ->allowEmptyString('queue');

        $validator
            ->integer('priority')
            ->allowEmptyString('priority');

        $validator
            ->integer('delayed_until')
            ->allowEmptyString('delayed_until');

        $validator
            ->integer('time_to_live')
            ->allowEmptyString('time_to_live');

        $validator
            ->integer('redeliver_after')
            ->allowEmptyString('redeliver_after');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        return $rules;
    }
}
