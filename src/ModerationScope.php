<?php

namespace Hootlex\Moderation;

use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ScopeInterface;

class ModerationScope implements ScopeInterface
{
    /**
     * All of the extensions to be added to the builder.
     *
     * @var array
     */
    protected $extensions = [
        'WithPending',
        'WithRejected',
        'WithPostponed',
        'WithAnyStatus',
        'Pending',
        'Rejected',
        'Postponed',
        'Approve',
        'Reject',
        'Postpone',
    ];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     * @param  \Illuminate\Database\Eloquent\Model $model
     *
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $strict = (isset($model::$strictModeration))
            ? $model::$strictModeration
            : config('moderation.strict');

        if ($strict) {
            $builder->where($model->getQualifiedStatusColumn(), '=', config('moderation.status.approved'));
        } else {
            $builder->whereIn($model->getStatusColumn(), [config('moderation.status.approved'), config('moderation.status.pending')]);
        }

        $this->extend($builder);
    }

    /**
     * Remove the scope from the given Eloquent query builder.
     *
     * (This method exists in order to achieve compatibility with laravel 5.1.*)
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function remove(Builder $builder, Model $model)
    {
        $column = $model->getQualifiedStatusColumn();
        $query = $builder->getQuery();

        $bindingKey = 0;

        foreach ((array)$query->wheres as $key => $where) {
            if ($this->isModerationConstraint($where, $column)) {
                $this->removeWhere($query, $key);

                // Here SoftDeletingScope simply removes the where
                // but since we use Basic where (not Null type)
                // we need to get rid of the binding as well
                $this->removeBinding($query, $bindingKey);
            }

            // Check if where is either NULL or NOT NULL type,
            // if that's the case, don't increment the key
            // since there is no binding for these types
            if (!in_array($where['type'], ['Null', 'NotNull'])) $bindingKey++;
        }

    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }

        $builder->onDelete(function (Builder $builder) {
            $column = $builder->getModel()->getModeratedAtColumn();

            return $builder->update([
                $column => $builder->getModel()->freshTimestampString(),
            ]);
        });
    }

    /**
     * Add the with-pending extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addWithPending(Builder $builder)
    {
        $builder->macro('withPending', function (Builder $builder) {
            $this->remove($builder, $builder->getModel());

            return $builder->whereIN($this->getStatusColumn($builder), [config('moderation.status.approved'), config('moderation.status.pending')]);
        });
    }

    /**
     * Add the with-rejected extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addWithRejected(Builder $builder)
    {
        $builder->macro('withRejected', function (Builder $builder) {
            $this->remove($builder, $builder->getModel());

            return $builder->whereIN($this->getStatusColumn($builder),
                [config('moderation.status.approved'), config('moderation.status.rejected')]);
        });
    }

    /**
     * Add the with-postpone extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addWithPostponed(Builder $builder)
    {
        $builder->macro('withPostponed', function (Builder $builder) {
            $this->remove($builder, $builder->getModel());

            return $builder->whereIN($this->getStatusColumn($builder),
                [config('moderation.status.approved'), config('moderation.status.postponed')]);
        });
    }

    /**
     * Add the with-any-status extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addWithAnyStatus(Builder $builder)
    {
        $builder->macro('withAnyStatus', function (Builder $builder) {
            $this->remove($builder, $builder->getModel());
            return $builder;
        });
    }

    /**
     * Add the Pending extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addPending(Builder $builder)
    {
        $builder->macro('pending', function (Builder $builder) {
            $model = $builder->getModel();

            $this->remove($builder, $model);

            $builder->where($model->getQualifiedStatusColumn(), '=', config('moderation.status.pending'));

            return $builder;
        });
    }

    /**
     * Add the Rejected extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addRejected(Builder $builder)
    {
        $builder->macro('rejected', function (Builder $builder) {
            $model = $builder->getModel();

            $this->remove($builder, $model);

            $builder->where($model->getQualifiedStatusColumn(), '=', config('moderation.status.rejected'));

            return $builder;
        });
    }

    /**
     * Add the Postponed extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addPostponed(Builder $builder)
    {
        $builder->macro('postponed', function (Builder $builder) {
            $model = $builder->getModel();

            $this->remove($builder, $model);

            $builder->where($model->getQualifiedStatusColumn(), '=', config('moderation.status.postponed'));

            return $builder;
        });
    }

    /**
     * Add the Approve extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addApprove(Builder $builder)
    {
        $builder->macro('approve', function (Builder $builder, $id = null) {
            $builder->withAnyStatus();
            return $this->updateModerationStatus($builder, $id, config('moderation.status.approved'));
        });
    }

    /**
     * Add the Reject extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addReject(Builder $builder)
    {
        $builder->macro('reject', function (Builder $builder, $id = null) {
            $builder->withAnyStatus();
            return $this->updateModerationStatus($builder, $id, config('moderation.status.rejected'));

        });
    }

    /**
     * Add the Postpone extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return void
     */
    protected function addPostpone(Builder $builder)
    {
        $builder->macro('postpone', function (Builder $builder, $id = null) {
            $builder->withAnyStatus();
            return $this->updateModerationStatus($builder, $id, config('moderation.status.postponed'));
        });
    }

    /**
     * Get the "deleted at" column for the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     *
     * @return string
     */
    protected function getStatusColumn(Builder $builder)
    {
        if (count($builder->getQuery()->joins) > 0) {
            return $builder->getModel()->getQualifiedStatusColumn();
        } else {
            return $builder->getModel()->getStatusColumn();
        }
    }

    /**
     * Remove scope constraint from the query.
     *
     * @param $query
     * @param  int $key
     *
     * @internal param \Illuminate\Database\Query\Builder $builder
     */
    protected function removeWhere($query, $key)
    {
        unset($query->wheres[$key]);

        $query->wheres = array_values($query->wheres);
    }

    /**
     * Remove scope constraint from the query.
     *
     * @param $query
     * @param  int $key
     *
     * @internal param \Illuminate\Database\Query\Builder $builder
     */
    protected function removeBinding($query, $key)
    {
        $bindings = $query->getRawBindings()['where'];

        unset($bindings[$key]);

        $query->setBindings($bindings);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param $id
     * @param $status
     *
     * @return bool|int
     */
    private function updateModerationStatus(Builder $builder, $id, $status)
    {

        //If $id parameter is passed then update the specified model
        if ($id) {
            $model = $builder->find($id);
            $model->{$model->getStatusColumn()} = $status;
            $model->{$model->getModeratedAtColumn()} = Carbon::now();
            //if moderated_by in enabled then append it to the update
            if ($moderated_by = $model->getModeratedByColumn()) {
                $model->{$moderated_by} = \Auth::user()->getKey();
            }

            return $model->save();
        }

        $update = [
            $builder->getModel()->getStatusColumn() => $status,
            $builder->getModel()->getModeratedAtColumn() => Carbon::now()
        ];
        //if moderated_by in enabled then append it to the update
        if ($moderated_by = $builder->getModel()->getModeratedByColumn()) {
            $update[$builder->getModel()->getModeratedByColumn()] = \Auth::user()->getKey();
        }
        return $builder->update($update);
    }

    /**
     * Determine if the given where clause is a moderation constraint.
     *
     * @param  array $where
     * @param  string $column
     * @return bool
     */
    protected function isModerationConstraint(array $where, $column)
    {
        return $where['column'] == $column;
    }
}
