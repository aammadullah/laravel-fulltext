<?php

namespace Swis\Laravel\Fulltext;

class Search implements SearchInterface
{
    /**
     * @param string $search
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Swis\Laravel\Fulltext\IndexedRecord[]
     */
    public function run($search,$paginate=false,$per_page=10)
    {
        $query = $this->searchQuery($search);
        if($paginate)
            return $query->paginate($per_page);
        else
            return $query->get();
    }

    /**
     * @param $search
     * @param $class
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Swis\Laravel\Fulltext\IndexedRecord[]
     */
    public function runForClass($search, $class)
    {
        $query = $this->searchQuery($search);
        $query->where('indexable_type', $class);

        return $query->get();
    }

    /**
     * @param string $search
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function searchQuery($search)
    {
        $termsBool = '';
        $termsMatch = '';

        if ($search) {
            $terms = TermBuilder::terms($search);

            $termsBool = '+'.$terms->implode(' +');
            $termsMatch = ''.$terms->implode(' ');
        }

        $titleWeight = str_replace(',', '.', (float) config('laravel-fulltext.weight.title', 1.5));
        $contentWeight = str_replace(',', '.', (float) config('laravel-fulltext.weight.content', 1.0));

        $query = IndexedRecord::query()
          ->whereRaw('MATCH (indexed_title, indexed_content) AGAINST (? IN BOOLEAN MODE)', [$termsBool])
          ->orderByRaw(
              '('.$titleWeight.' * (MATCH (indexed_title) AGAINST (?)) +
              '.$contentWeight.' * (MATCH (indexed_title, indexed_content) AGAINST (?))
             ) DESC',
                [$termsMatch, $termsMatch])
            ->limit(config('laravel-fulltext.limit-results'));

        if (config('laravel-fulltext.exclude_feature_enabled')) {
            $query->with(['indexable' => function ($query) {
                $query->where(config('laravel-fulltext.exclude_records_column_name'), '=', true);
            }]);
        } else {
            $query->with('indexable');
        }

        return $query;
    }
}
