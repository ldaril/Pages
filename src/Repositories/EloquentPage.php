<?php

namespace TypiCMS\Modules\Pages\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use TypiCMS\Modules\Core\Repositories\RepositoriesAbstract;

class EloquentPage extends RepositoriesAbstract implements PageInterface
{
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Update an existing model.
     *
     * @param array  Data needed for model update
     *
     * @return bool
     */
    public function update(array $data)
    {
        $model = $this->model->find($data['id']);

        $model->fill($data);

        $this->syncRelation($model, $data, 'galleries');

        if ($model->save()) {
            event('page.resetChildrenUri', [$model]);

            return true;
        }

        return false;
    }

    /**
     * Get a page by its uri.
     *
     * @param string $uri
     * @param string $locale
     * @param array  $with
     *
     * @return TypiCMS\Modules\Models\Page $model
     */
    public function getFirstByUri($uri, $locale, array $with = [])
    {
        $query = $this->make($with)
            ->where('uri->'.$locale, $uri);
        if (!Request::input('preview')) {
            $query->where('status->'.$locale, '1');
        }

        return $query->firstOrFail();
    }

    /**
     * Get submenu for a page.
     *
     * @return Collection
     */
    public function getSubMenu($uri, $all = false)
    {
        $rootUriArray = explode('/', $uri);
        $uri = $rootUriArray[0];
        $locale = config('app.locale');
        if (in_array($uri, config('translatable-bootforms.locales'))) {
            if (isset($rootUriArray[1])) { // i
                $uri .= '/'.$rootUriArray[1]; // add next part of uri in locale
            }
        }

        $query = $this->model
            ->where('uri->'.$locale, '!=', $uri)
            ->where('uri->'.$locale, 'LIKE', $uri.'%');

        if (!$all) {
            $query->where('status->'.$locale, '1');
        }

        $models = $query->order()->get()->nest();

        return $models;
    }

    /**
     * Get pages linked to a module.
     *
     * @return array
     */
    public function getForRoutes()
    {
        $pages = $this->make()
            ->where('module', '!=', '')
            ->get()
            ->all();

        return $pages;
    }

    /**
     * Get sort data.
     *
     * @param int   $position
     * @param array $item
     *
     * @return array
     */
    protected function getSortData($position, $item)
    {
        return [
            'position'  => $position,
            'parent_id' => $item['parent_id'],
        ];
    }

    /**
     * Get all translated pages for a select/options.
     *
     * @return array
     */
    public function allForSelect()
    {
        $pages = $this->all([], true)
            ->nest()
            ->listsFlattened();

        return ['' => ''] + $pages;
    }

    /**
     * Fire event to reset children’s uri
     * Only applicable on nestable collections.
     *
     * @param Page $page
     *
     * @return null|null
     */
    protected function fireResetChildrenUriEvent($page)
    {
        event('page.resetChildrenUri', [$page]);
    }
}
