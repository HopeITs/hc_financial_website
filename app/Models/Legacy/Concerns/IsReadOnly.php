<?php

namespace App\Models\Legacy\Concerns;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Makes a model of the Windows program's tables refuse every write.
 *
 * The database user behind the `legacy` connection has SELECT only, so a write would fail
 * there anyway; this fails earlier, with a clear message, before any query is sent.
 */
trait IsReadOnly
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): never
    {
        throw $this->readOnly('save');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): never
    {
        throw $this->readOnly('update');
    }

    public function delete(): never
    {
        throw $this->readOnly('delete');
    }

    public function forceDelete(): never
    {
        throw $this->readOnly('forceDelete');
    }

    /**
     * Guards the paths that skip save(): increment(), decrement() and push().
     *
     * @param  Builder<static>  $query
     */
    protected function performInsert($query): never
    {
        throw $this->readOnly('insert');
    }

    /**
     * @param  Builder<static>  $query
     */
    protected function performUpdate($query): never
    {
        throw $this->readOnly('update');
    }

    protected function performDeleteOnModel(): never
    {
        throw $this->readOnly('delete');
    }

    /**
     * increment() and decrement() update through the query builder, not through performUpdate().
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     * @param  string  $method
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method): never
    {
        throw $this->readOnly($method);
    }

    private function readOnly(string $operation): LogicException
    {
        return new LogicException(sprintf('%s is a read-only legacy model: cannot %s.', static::class, $operation));
    }
}
