<?php

namespace Vaharadev\LaravelClient\Util;

use Vaharadev\LaravelClient\Models\VaharaItem;
use Vaharadev\LaravelClient\Models\VaharaItemPivot;
use Illuminate\Support\Facades\DB;
use Exception;

class VaharaUtil
{
    /**
     * @param $id
     *
     * @return string[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updateData($id = null): array
    {
        // reach out to orbit and validate the token
        if ($id) {
            // Don't delete any items if we are just receiving one
            $deletingItems = false;
            $response = $this->api('/items/get', ['id' => $id]);
        } else {
            $deletingItems = true;
            $response = $this->api('/items/get');
        }

        if (isset($response['success']) && $response['success']) {

            if ($deletingItems) {
                $currentItemIdRows = DB::select('select id from vahara_items');
                $itemsToDelete = [];
                foreach ($currentItemIdRows as $row) {
                    $itemsToDelete[$row->id] = 1;
                }
            }

            $data = $response['data']['items_with_children'];

            foreach ($data as $row => $item) {
                if (is_string($item)) {
                    $jsonItem = $item;
                    $item = json_decode($item, 1);
                } else {
                    $jsonItem = json_encode($item);
                }

                if (isset($item['_meta']['id'])) {

                    $locale = '';
                    if (isset($item['_meta']['locale'])) {
                        $locale = $item['_meta']['locale'];
                    } else {
                        $locale = '';
                    }

                    $existingRow = DB::select('select * from vahara_items where id=? and locale=? limit 1',
                        [$item['_meta']['id'], $locale]);

                    if (isset($existingRow[0])) {
                        // Update existing
                        DB::update('update vahara_items set data=?,project_key=?,updated_at=NOW() where id=? and locale=?',
                            [$jsonItem, $item['_meta']['project_key'], $item['_meta']['id'], $locale]);

                    } else {
                        // Add new
                        DB::insert('insert into vahara_items (id,project_key,locale,data,type,created_at,updated_at) values (?,?,?,?,?,NOW(),NOW())',
                            [
                                $item['_meta']['id'], $item['_meta']['project_key'], $locale, $jsonItem,
                                $item['_meta']['type']
                            ]);
                    }

                    if ($deletingItems) {
                        unset($itemsToDelete[$item['_meta']['id']]);
                    }
                }
            }

            if ($deletingItems && count($itemsToDelete)) {
                $keys = implode(',', array_keys($itemsToDelete));
                DB::statement('delete from vahara_items where id in ('.$keys.')');
                DB::statement('delete from vahara_item_pivot where parent_id in ('.$keys.') or child_id in ('.$keys.')');
            }
        }

        $localeIds = [];

        if (function_exists('getLocales')) {
            $locales = \getLocales();
            foreach ($locales as $key => $name) {
                if (strlen($key)) {
                    $localeIds[] = $key;
                }
            }

            // Make duplicate rows for any items that have no translations
            // So that there is a row of each item for each translation
            $allTranslations = DB::select("select originals.id, locales.locales
                                                    from (select id from vahara_items where locale = '') originals
                                                             LEFT OUTER JOIN (select id, string_agg(locale, ',') as locales
                                                                              from vahara_items
                                                                              where locale != ''
                                                                              group by id) locales
                                                                             ON locales.id = originals.id");

            foreach ($allTranslations as $translation) {
                $existingTranslations = explode(',', $translation->locales);

                foreach ($localeIds as $id) {
                    if (!in_array($id, $existingTranslations)) {
                        //print $id . " for " . $translation->id . " doesn't exist.<bR>\n";

                        // Create a copy of the original with the new locale
                        DB::insert("INSERT INTO vahara_items
                                            (id,project_key,type,locale,data,created_at,updated_at)
                                                SELECT id,project_key,type,'{$id}',data,created_at,updated_at FROM vahara_items
                                                where id=? and locale=''", [$translation->id]);

                    } else {
                        //print "***" . $id . " for " . $translation->id . " DOES exist.<bR>\n";
                    }
                }
            }
        }

        return (['status' => 'ok']);
    }

    /**
     * @param $request
     * @param  array  $arguments
     * @param  string  $version
     *
     * @return mixed|void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function api($request, array $arguments = [], string $version = '1')
    {
        $arguments['rk'] = getenv('VAHARA_BACKEND_KEY');
        $arguments['pid'] = getenv('VAHARA_PROJECT_ID');

        $client = new \GuzzleHttp\Client([
            'verify' => in_array(getenv('APP_ENV'), ['prod', 'live', 'production']),
            'timeout' => 300
        ]);

        $requestUrl = getenv('VAHARA_SERVER') . '/api/backend/V' . $version . $request;

        try {
            $res = $client->request('POST', $requestUrl, [
                'form_params' => $arguments
            ]);

            return json_decode($res->getBody()->getContents(), true);

        } catch (\GuzzleHttp\Exception\GuzzleException $ex) {

            print "Unable to connect, please try again.\n";
            exit;

        } catch (Exception $ex) {

            sleep(5);

            try {
                $res = $client->request('POST', $requestUrl, [
                    'form_params' => $arguments
                ]);

                return json_decode($res->getBody()->getContents(), true);

            } catch (Exception $ex) {
                print "Unable to connect, please try again.\n";
                exit;
            }
        }
    }

    /**
     * @return string[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function updateRelationalData(): array
    {
        $response = $this->api('/items/get-all-relations', []);

        if (isset($response['success']) && $response['success']) {
            $data = $response['data'];
            $existingItemPivotList = VaharaItemPivot::select(['parent_id', 'child_id', 'relationship', 'sort'])->get();
            $existingItemPivotArr = count($existingItemPivotList->toArray()) > 0 ? $existingItemPivotList->toArray() : [];

            $pivotToAdd = array_filter($data, function ($element) use ($existingItemPivotArr) {
                return !in_array($element, $existingItemPivotArr);
            });

            if ($pivotToAdd) {
                foreach ($pivotToAdd as $row => $item) {
                    $childId = $item['child_id'];
                    $parentId = $item['parent_id'];
                    $relationship = $item['relationship'];
                    $sort = $item['sort'];

                    $vaharaItemPivot = VaharaItemPivot::whereChildId($childId)
                        ->whereParentId($parentId)
                        ->whereRelationship($relationship)
                        ->first();

                    if (empty($vaharaItemPivot)) {
                        $vaharaItemPivot = new VaharaItemPivot();
                    }

                    $vaharaItemPivot->child_id = $childId;
                    $vaharaItemPivot->parent_id = $parentId;
                    $vaharaItemPivot->relationship = $relationship;
                    $vaharaItemPivot->sort = $sort;
                    $vaharaItemPivot->save();
                }
            }

            $existingItemPivotList = VaharaItemPivot::select(['parent_id', 'child_id', 'relationship', 'sort'])->get();
            $existingItemPivotArr = count($existingItemPivotList->toArray()) > 0 ? $existingItemPivotList->toArray() : [];

            $pivotToRemove = array_filter($existingItemPivotArr, function ($element) use ($data) {
                return !in_array($element, $data);
            });

            if ($pivotToRemove) {
                foreach ($pivotToRemove as $row => $item) {
                    $childId = $item['child_id'];
                    $parentId = $item['parent_id'];
                    $relationship = $item['relationship'];

                    $vaharaItemPivot = VaharaItemPivot::whereChildId($childId)
                        ->whereParentId($parentId)
                        ->whereRelationship($relationship)
                        ->first();

                    if (!empty($vaharaItemPivot)) {
                        $vaharaItemPivot->delete();
                    }
                }
            }
        }

        return (['status' => 'ok']);
    }
}
