<?php

namespace IM;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

class Application extends Container
{
    /**
     * The base path of the application installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * All of the loaded configuration files.
     *
     * @var array
     */
    protected $loadedConfigurations = [];

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * The loaded service providers.
     *
     * @var array
     */
    protected $loadedProviders = [];

    /**
     * The service binding methods that have been executed.
     *
     * @var array
     */
    protected $ranServiceBinders = [];

    /**
     * The Router instance.
     *
     * @var IMServer
     */
    public $IM;

    public function __construct($basePath = null) {
        if (!empty(env('APP_TIMEZONE'))) {
            date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));
        }

        $this->basePath = $basePath;

        $this->bootstrapContainer();
        $this->bootstrapIM();
    }

    /**
     * Bootstrap the application container.
     *
     * @return void
     */
    protected function bootstrapContainer() {
        static::setInstance($this);
        $this->instance('app', $this);
        $this->instance(self::class, $this);
        $this->registerContainerAliases();
    }

    /**
     * Bootstrap the router instance.
     *
     * @return void
     */
    public function bootstrapIM()
    {
        $this->IM = new IMServer($this);
    }

    /**
     * Resolve the given type from the container.
     *
     * @param  string $abstract
     * @param  array  $parameters
     *
     * @return mixed
     */
    public function make($abstract, array $parameters = []) {
        $abstract = $this->getAlias($abstract);
        if (!$this->bound($abstract) &&
            array_key_exists($abstract, $this->availableBindings) &&
            !array_key_exists($this->availableBindings[$abstract], $this->ranServiceBinders)) {
            $this->{$method = $this->availableBindings[$abstract]}();
            $this->ranServiceBinders[$method] = true;
        }
        return parent::make($abstract, $parameters);
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerConfigBindings() {
        $this->singleton('config', function () {
            return new Repository();
        });
    }

    /**
     * Load a configuration file into the application.
     *
     * @param  string $name
     *
     * @return void
     */
    public function configure($name) {
        if (isset($this->loadedConfigurations[$name])) {
            return;
        }
        $this->loadedConfigurations[$name] = true;
        $path                              = $this->getConfigurationPath($name);
        if ($path) {
            $this->make('config')->set($name, require $path);
        }
    }

    /**
     * Get the base path for the application.
     *
     * @param  string|null $path
     *
     * @return string
     */
    public function basePath($path = null) {
        if (isset($this->basePath)) {
            return $this->basePath . ($path ? '/' . $path : $path);
        }
        if ($this->runningInConsole()) {
            $this->basePath = getcwd();
        } else {
            $this->basePath = realpath(getcwd() . '/../');
        }
        return $this->basePath($path);
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole() {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * Get the path to the given configuration file.
     *
     * If no name is provided, then we'll return the path to the config folder.
     *
     * @param  string|null $name
     *
     * @return string
     */
    public function getConfigurationPath($name = null) {
        if (!$name) {
            $appConfigDir = $this->basePath('config') . '/';
            if (file_exists($appConfigDir)) {
                return $appConfigDir;
            } elseif (file_exists($path = __DIR__ . '/../config/')) {
                return $path;
            }
        } else {
            $appConfigPath = $this->basePath('config') . '/' . $name . '.php';
            if (file_exists($appConfigPath)) {
                return $appConfigPath;
            } elseif (file_exists($path = __DIR__ . '/../config/' . $name . '.php')) {
                return $path;
            }
        }
    }

    /**
     * Register a service provider with the application.
     *
     * @param  \Illuminate\Support\ServiceProvider|string $provider
     *
     * @return bool
     */
    public function register($provider) {
        if (!$provider instanceof ServiceProvider) {
            $provider = new $provider($this);
        }
        if (array_key_exists($providerName = get_class($provider), $this->loadedProviders)) {
            return false;
        }
        $this->loadedProviders[$providerName] = $provider;
        if (method_exists($provider, 'register')) {
            $provider->register();
        }
        if ($this->booted) {
            $this->bootProvider($provider);
        }
        return true;
    }

    /**
     * Boots the registered providers.
     */
    public function boot() {
        if ($this->booted) {
            return;
        }
        array_walk($this->loadedProviders, function ($p) {
            $this->bootProvider($p);
        });
        $this->booted = true;
    }

    /**
     * Boot the given service provider.
     *
     * @param  \Illuminate\Support\ServiceProvider $provider
     *
     * @return void
     */
    protected function bootProvider(ServiceProvider $provider) {
        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }
    }

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush()
    {
        parent::flush();
        $this->loadedProviders = [];
        $this->reboundCallbacks = [];
        $this->resolvingCallbacks = [];
        $this->availableBindings = [];
        $this->ranServiceBinders = [];
        $this->loadedConfigurations = [];
        $this->afterResolvingCallbacks = [];
        $this->bootstrapContainer();
    }

    /**
     * Register the core container aliases.
     *
     * @return void
     */
    protected function registerContainerAliases() {
        $this->aliases = [
            'Illuminate\Contracts\Config\Repository' => 'config'
        ];
    }

    /**
     * The available container bindings and their respective load methods.
     *
     * @var array
     */
    public $availableBindings = [
        'config' => 'registerConfigBindings'
    ];
}