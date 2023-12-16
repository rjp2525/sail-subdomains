<?php

namespace Reno\SailSubdomains\Console;

use Illuminate\Console\Command;

class PublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:publish-subdomains {--runtime=8.2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Modifies the published Sail Docker configurations to enable subdomains locally.';

    /**
     * The runtime version to modify.
     *
     * @var string
     */
    protected string $version = '8.2';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        // Default PHP version 8.2
        $this->version = $this->option('runtime');

        $this->updateDockerfile();
        $this->updateSupervisorConfiguration();
        $this->updateNginxConfiguration();
        // Update docker-composer.yml for volumes:
        // nginx/sites -> nginx/sites-available
    }

    // TODO: update the laravel.conf site with runtime version
    protected function updateNginxConfiguration(): bool
    {
        $nginxConfPath = $this->laravel->basePath("docker/{$this->version}/nginx/nginx.conf");

        if (!file_exists($nginxConfPath)) {
            $this->error("Required nginx configuration file not found.");
            return true;
        }

        $content = file_get_contents($nginxConfPath);

        $lineUpdates = file_get_contents($nginxConfPath);
        $lineUpdates = str_replace('{RUNTIME_VERSION}', $this->version, $lineUpdates);

        file_put_contents($nginxConfPath, $content);

        $this->info('nginx configuration file successfully updated.');
    }

    protected function updateSupervisorConfiguration(): bool
    {
        $supervisorConfPath = $this->laravel->basePath("docker/{$this->version}/supervisord.conf");
        $importPath = __DIR__ . '../../import/supervisor-config-updates';

        if (!file_exists($supervisorConfPath) || !file_exists($importPath)) {
            $this->error("Required supervisor configuration file not found.");
            return true;
        }

        $content = file_get_contents($supervisorConfPath);

        $lineUpdates = file_get_contents($importPath);
        $lineUpdates = str_replace('{RUNTIME_VERSION}', $this->version, $lineUpdates);

        $content = $this->replaceSupervisorPhpBlock($content, $lineUpdates);

        file_put_contents($supervisorConfPath, $content);

        $this->info('Supervisor configuration file successfully updated.');
    }

    protected function replaceSupervisorPhpBlock(string $originalContent, string $newBlock): string
    {
        $pattern = '/\[program:php\].*?(?=\n\[|$)/s';

        return preg_replace($pattern, $newBlock, $originalContent);
    }

    protected function updateDockerfile(): bool
    {
        $dockerFilePath = $this->laravel->basePath("docker/{$this->version}/Dockerfile");

        // Ensure the Dockerfile exists
        if (!file_exists($dockerFilePath)) {
            $this->error("Dockerfile not found at {$dockerFilePath}");
            return true;
        }

        // Update Dockerfile
        $content = file_get_contents($dockerFilePath);

        // Install nginx, openssl, bash and curl
        $content = $this->addAdditionalPackagesToInstall($content);
        // Remove port exposure
        $content = $this->removePortExposure($content);
        // Add new lines to Dockerfile:
        // - copies nginx configuration
        // - enables the laravel scheduler via cron
        // - disables systemd process spawning for nginx and fpm)
        // - initializes the log files and ensures correct owner permissions
        $content = $this->addNewLinesToDockerFile($content);
        // Copies the www.conf for php-fpm
        $content = $this->addFpmCopyBeforeChmod($content);

        file_put_contents($dockerFilePath, $content);

        $this->info("Dockerfile for runtime {$this->version} successfully updated.");
    }

    protected function addAdditionalPackagesToInstall(string $content): string
    {
        $aptInstallPattern = '/(RUN apt-get update \\\s+&& apt-get install -y)([^&]+)/';
        $aptInstallReplacement = '$1$2 nginx openssl bash curl';

        return preg_replace($aptInstallPattern, $aptInstallReplacement, $content);
    }

    protected function removePortExposure(string $content): string
    {
        $exposePortPattern = '/\n\s*\nEXPOSE 8000\n/';

        return preg_replace($exposePortPattern, '\n', $content);
    }

    protected function addNewLinesToDockerFile(string $content): string
    {
        $importPath = __DIR__ . '../../import/dockerfile-line-updates';
        $lineUpdates = file_get_contents($importPath);
        $lineUpdates = str_replace('{RUNTIME_VERSION}', $this->version, $lineUpdates);

        $insertAfterPattern = '/(RUN useradd -ms \/bin\/bash --no-user-group -g \$WWWGROUP -u 1337 sail)/';

        return preg_replace($insertAfterPattern, '$1\n\n' . $lineUpdates, $content);

    }

    protected function addFpmCopyBeforeChmod(string $content): string
    {
        $fpmCopyLine = "COPY www.conf /etc/php/8.2/fpm/pool.d/www.conf\n";
        $chmodPattern = '/(RUN chmod \+x \/usr\/local\/bin\/start-container)/';

        return preg_replace($chmodPattern, $fpmCopyLine . '$1', $content);
    }
}
