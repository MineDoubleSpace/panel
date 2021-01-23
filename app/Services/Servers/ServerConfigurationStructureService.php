<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Mount;
use Pterodactyl\Models\Server;

class ServerConfigurationStructureService
{
    /**
     * @var \Pterodactyl\Services\Servers\EnvironmentService
     */
    private $environment;

    /**
     * ServerConfigurationStructureService constructor.
     *
     * @param \Pterodactyl\Services\Servers\EnvironmentService $environment
     */
    public function __construct(EnvironmentService $environment)
    {
        $this->environment = $environment;
    }

    /**
     * Return a configuration array for a specific server when passed a server model.
     *
     * DO NOT MODIFY THIS FUNCTION. This powers legacy code handling for the new Wings
     * daemon, if you modify the structure eggs will break unexpectedly.
     *
     * @param \Pterodactyl\Models\Server $server
     * @param array $override
     * @param bool $legacy deprecated
     * @return array
     */
    public function handle(Server $server, array $override = [], bool $legacy = false): array
    {
        $clone = $server;
        // If any overrides have been set on this call make sure to update them on the
        // cloned instance so that the configuration generated uses them.
        if (! empty($override)) {
            $clone = $server->fresh();
            foreach ($override as $key => $value) {
                $clone->setAttribute($key, $value);
            }
        }

        return $legacy
            ? $this->returnLegacyFormat($clone)
            : $this->returnCurrentFormat($clone);
    }

    /**
     * Returns the new data format used for the Wings daemon.
     *
     * @param \Pterodactyl\Models\Server $server
     * @return array
     */
    protected function returnCurrentFormat(Server $server)
    {
        return [
            'uuid' => $server->uuid,
            'suspended' => $server->suspended,
            'environment' => $this->environment->handle($server),
            'invocation' => $server->startup,
            'skip_egg_scripts' => $server->skip_scripts,
            'build' => [
                'memory_limit' => $server->memory,
                'swap' => $server->swap,
                'io_weight' => $server->io,
                'cpu_limit' => $server->cpu,
                'threads' => $server->threads,
                'disk_space' => $server->disk,
            ],
            'container' => [
                'image' => $server->image,
                'oom_disabled' => $server->oom_disabled,
                'requires_rebuild' => false,
            ],
            'allocations' => [
                'default' => [
                    'ip' => $server->allocation->ip,
                    'port' => $server->allocation->port,
                ],
                'mappings' => $server->getAllocationMappings(),
            ],
            'mounts' => $server->mounts->map(function (Mount $mount) {
                return [
                    'source' => $mount->source,
                    'target' => $mount->target,
                    'read_only' => $mount->read_only,
                ];
            }),
            'egg' => [
                'id' => $server->egg->uuid,
                'file_denylist' => [
                    'config.yml',
                    '**/*.json',
                ],
                // 'file_denylist' => explode(PHP_EOL, $server->egg->inherit_file_denylist),
            ],
        ];
    }

    /**
     * Returns the legacy server data format to continue support for old egg configurations
     * that have not yet been updated.
     *
     * @param \Pterodactyl\Models\Server $server
     * @return array
     * @deprecated
     */
    protected function returnLegacyFormat(Server $server)
    {
        return [
            'uuid' => $server->uuid,
            'build' => [
                'default' => [
                    'ip' => $server->allocation->ip,
                    'port' => $server->allocation->port,
                ],
                'ports' => $server->allocations->groupBy('ip')->map(function ($item) {
                    return $item->pluck('port');
                })->toArray(),
                'env' => $this->environment->handle($server),
                'oom_disabled' => $server->oom_disabled,
                'memory' => (int) $server->memory,
                'swap' => (int) $server->swap,
                'io' => (int) $server->io,
                'cpu' => (int) $server->cpu,
                'threads' => $server->threads,
                'disk' => (int) $server->disk,
                'image' => $server->image,
            ],
            'service' => [
                'egg' => $server->egg->uuid,
                'skip_scripts' => $server->skip_scripts,
            ],
            'rebuild' => false,
            'suspended' => (int) $server->suspended,
        ];
    }
}
