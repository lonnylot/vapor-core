<?php

namespace Laravel\Vapor\Runtime;

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

class Environment
{
    /**
     * The writable path for the environment file.
     *
     * @var string
     */
    protected $writePath = '/tmp';

    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The environment name.
     *
     * @var string
     */
    protected $environment;

    /**
     * The environment file name.
     *
     * @var string
     */
    protected $environmentFile;

    /**
     * The encrypted environment file name.
     *
     * @var string
     */
    protected $encryptedFile;

    /**
     * The console kernel instance.
     *
     * @var \Illuminate\Contracts\Console\Kernel
     */
    protected $console;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->environment = isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'production';
        $this->environmentFile = '.env.'.$this->environment;
        $this->encryptedFile = '.env.'.$this->environment.'.encrypted';
    }

    /**
     * Decrypt the environment file and load it into the runtime.
     *
     * @return void
     */
    public function decryptEnvironment()
    {
        $basePath = $this->app->basePath();

        try {
            if (! $this->canBeDecrypted()) {
                return;
            }

            $this->copyEncryptedFile();

            $this->app->setBasePath($this->writePath);

            $this->decryptFile();

            $this->loadEnvironment();
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage().PHP_EOL);
        }

        $this->app->setBasePath($basePath);
    }

    /**
     * Decrypt the environment file and load it into the runtime.
     *
     * @return void
     */
    public static function decrypt($app)
    {
        (new static($app))->decryptEnvironment();
    }

    /**
     * Determine if it is possible to decrypt an environment file.
     *
     * @return bool
     */
    public function canBeDecrypted()
    {
        if (! isset($_ENV['LARAVEL_ENV_ENCRYPTION_KEY'])) {
            fwrite(STDERR, 'No decryption key set.'.PHP_EOL);

            return false;
        }

        if (! in_array('env:decrypt', array_keys($this->console()->all()))) {
            fwrite(STDERR, 'Decrypt command not available.'.PHP_EOL);

            return false;
        }

        if (! file_exists($this->app->basePath($this->encryptedFile))) {
            fwrite(STDERR, 'Encrypted environment file not found.'.PHP_EOL);

            return false;
        }

        return true;
    }

    /**
     * Copy the encrypted environment file to the writable path.
     *
     * @return void
     */
    public function copyEncryptedFile()
    {
        copy(
            $this->app->basePath($this->encryptedFile),
            $this->writePath.DIRECTORY_SEPARATOR.$this->encryptedFile
        );
    }

    /**
     * Decrypt the environment file.
     *
     * @return void
     */
    public function decryptFile()
    {
        fwrite(STDERR, 'Decrypting environment variables.'.PHP_EOL);

        $this->console()->call('env:decrypt', ['--env' => $this->environment]);
    }

    /**
     * Load the decrypted environment file.
     *
     * @return void
     */
    public function loadEnvironment()
    {
        fwrite(STDERR, 'Loading decrypted environment variables.'.PHP_EOL);

        Dotenv::createImmutable($this->app->basePath(), $this->environmentFile)->load();
    }

    /**
     * Get a console kernel instance.
     *
     * @return \Illuminate\Contracts\Console\Kernel
     */
    public function console()
    {
        if (! $this->console) {
            $this->console = $this->app->make(Kernel::class);
        }

        return $this->console;
    }
}
