<?php

namespace Livewire\Features;

use Synthetic\Utils as SyntheticUtils;
use Livewire\Synthesizers\LivewireSynth;
use Livewire\Mechanisms\ComponentDataStore;

class SupportReactiveProps
{
    public static $pendingChildParams = [];

    public function boot()
    {
        app('synthetic')->on('flush-state', fn() => static::$pendingChildParams = []);


        app('synthetic')->on('mount', function ($name, $params, $parent, $key, $slots, $hijack) {
            return function ($target) use ($params) {
                $props = [];

                foreach (SyntheticUtils::getAnnotations($target) as $key => $value) {
                    if (isset($value['prop']) && isset($params[$key])) {
                        $props[] = $key;
                    }
                }

                ComponentDataStore::set($target, 'props', $props);

                return $target;
            };
        });

        app('synthetic')->on('dummy-mount', function ($tag, $id, $params, $parent, $key) {
            $this->storeChildParams($id, $params);
        });

        app('synthetic')->on('dehydrate', function ($synth, $target, $context) {
            if (! $synth instanceof LivewireSynth) return;

            $props = ComponentDataStore::get($target, 'props', []);
            $propHashes = ComponentDataStore::get($target, 'propHashes', []);

            foreach ($propHashes as $key => $hash) {
                if (crc32(json_encode($target->{$key})) !== $hash) {
                    throw new \Exception('Cant mutate a prop: ['.$key.']');
                }
            }

            $props && $context->addMeta('props', $props);
        });

        app('synthetic')->on('hydrate', function ($synth, $rawValue, $meta) {
            if (! $synth instanceof LivewireSynth) return;
            if (! isset($meta['props'])) return;

            $propKeys = $meta['props'];

            $props = static::getProps($meta['id'], $propKeys);

            return function ($target) use ($props, $propKeys) {
                $propHashes = [];

                foreach ($props as $key => $value) {
                    $target->{$key} = $value;
                }

                foreach ($propKeys as $key) {
                    $propHashes[$key] = crc32(json_encode($target->{$key}));
                }

                ComponentDataStore::set($target, 'props', $propKeys);
                ComponentDataStore::set($target, 'propHashes', $propHashes);

                return $target;
            };
        });
    }

    public static function storeChildParams($id, $params)
    {
        static::$pendingChildParams[$id] = $params;
    }

    public static function getProps($id, $propKeys)
    {
        $params = static::$pendingChildParams[$id] ?? [];

        $props = [];

        foreach ($params as $key => $value) {
            if (in_array($key, $propKeys)) {
                $props[$key] = $value;
            }
        }

        return $props;
    }

    public static function hasProp($id, $propKey)
    {
        $params = static::$pendingChildParams[$id] ?? [];

        foreach ($params as $key => $value) {
            if ($propKey === $key) return true;
        }

        return false;
    }
}
