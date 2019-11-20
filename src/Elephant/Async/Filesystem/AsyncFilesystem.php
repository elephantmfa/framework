<?php

namespace Elephant\Async\Filesystem;

// @todo THIS DOESN'T WORK.
//   Currently this uses React/filesystem but that doesn't seem to work properly.
//   Need to find a better solution.

use Clue\React\Block;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem as IlluminateFilesystem;

class AsyncFilesystem implements IlluminateFilesystem
{
    use Macroable;

    protected $app;
    protected $config;
    protected $filesystem;

    public function __construct($app, $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function exists($path)
    {
        $path = $this->config['root'] . "/$path";

        try {
            Block\await($this->getFilesystem()->file($path)->exists(), $this->app->loop, 5);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Determine if a file or directory is missing.
     *
     * @param  string  $path
     * @return bool
     */
    public function missing($path)
    {
        $path = $this->config['root'] . "/$path";

        return !$this->exists($path);
    }

    /**
     * {@inheritDoc}
     */
    public function get($path)
    {
        $path = $this->config['root'] . "/$path";
        if (! $this->exists($path)) {
            throw new FileNotFoundException("File does not exist at path {$path}");
        }

        return Block\await($this->getFilesystem()->getContents($path), $this->app->loop, 5);
    }

    /**
     * {@inheritDoc}
     */
    public function readStream($path)
    {
        $path = $this->config['root'] . "/$path";
    }

    /**
     * {@inheritDoc}
     */
    public function put($path, $contents, $options = [])
    {
        $path = $this->config['root'] . "/$path";

        try {
            Block\await($this->getFilesystem()->file($path)->putContents($contents), $this->app->loop, 1);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function writeStream($path, $resource, array $options = [])
    {
        $path = $this->config['root'] . "/$path";
    }

    /**
     * {@inheritDoc}
     */
    public function getVisibility($path)
    {
        $path = $this->config['root'] . "/$path";
    }

    /**
     * {@inheritDoc}
     */
    public function setVisibility($path, $visibility)
    {
        $path = $this->config['root'] . "/$path";
    }

    /**
     * {@inheritDoc}
     */
    public function prepend($path, $data)
    {
        $path = $this->config['root'] . "/$path";
        if ($this->exists($path)) {
            return $this->put($path, $data . $this->get($path));
        }

        return $this->put($path, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function append($path, $data)
    {
        $path = $this->config['root'] . "/$path";
        if (!$this->exists($path)) {
            throw new FileNotFoundException("File does not exist at path {$path}");
        }

        return Block\await($this->getFilesystem()->file($path)->open('cw')->then(function ($stream) use ($data) {
            try {
                $stream->write($data);
                $stream->end();

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }), $this->app->loop, 5);
    }

    /**
     * {@inheritDoc}
     */
    public function delete($paths)
    {
        $paths = is_array($paths) ? $paths : func_get_args();
        $success = true;

        foreach ($paths as $path) {
            $path = $this->config['root'] . "/$path";

            try {
                Block\await($this->getFilesystem()->dir($path)->remove(), $this->app->loop, 5);
                $success = true;
            } catch (ErrorException $e) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public function copy($from, $to)
    {
        $from = $this->config['root'] . "/$from";
        $to = $this->config['root'] . "/$to";

        return $this->getFilesystem()->file($from)
            ->copy($this->getFilesystem()->file($to));
    }

    /**
     * {@inheritDoc}
     */
    public function move($from, $to)
    {
        $from = $this->config['root'] . "/$from";
        $to = $this->config['root'] . "/$to";

        Block\await($this->getFilesystem()->file($from)->rename($to), $this->app->loop, 5);

        if ($this->copy($from, $to)) {
            return $this->delete($from);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function size($path)
    {
        $path = $this->config['root'] . "/$path";

        return Block\await($this->getFilesystem()->file($path)->size(), $this->app->loop, 5);
    }

    /**
     * {@inheritDoc}
     */
    public function lastModified($path)
    {
        $path = $this->config['root'] . "/$path";

        return Block\await($this->getFilesystem()->file($path)->time(), $this->app->loop, 5)['mtime'];
    }

    /**
     * {@inheritDoc}
     */
    public function files($directory = null, $recursive = false)
    {
        $directory = $this->config['root'] . "/$directory";

        if ($recursive) {
            return Block\await($this->getFilesystem()->dir($directory)->lsRecursive(), $this->app->loop, 5);
        }

        return Block\await($this->getFilesystem()->dir($directory)->ls(), $this->app->loop, 5);
    }

    /**
     * {@inheritDoc}
     */
    public function allFiles($directory = null)
    {
        return $this->files($directory, true);
    }

    /**
     * {@inheritDoc}
     */
    public function directories($directory = null, $recursive = false)
    {
        $directory = $this->config['root'] . "/$directory";

        $then = function (array $files) {
            return array_filter($files, 'is_dir');
        };

        if ($recursive) {
            return Block\await($this->getFilesystem()->dir($directory)->lsRecursive()->then($then), $this->app->loop, 5);
        }

        return Block\await($this->getFilesystem()->dir($directory)->ls()->then($then), $this->app->loop, 5);
    }

    /**
     * {@inheritDoc}
     */
    public function allDirectories($directory = null)
    {
        return $this->directories($directory, true);
    }

    /**
     * {@inheritDoc}
     */
    public function makeDirectory($path)
    {
        $path = $this->config['root'] . "/$path";

        return Block\await($this->getFilesystem()->dir($path)->create(), $this->app->loop, 5);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteDirectory($directory)
    {
        try {
            Block\await($this->getFilesystem()->dir($directory)->delete(), $this->app->loop, 5);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getFilesystem()
    {
        return $this->app[\React\Filesystem\Filesystem::class];
    }
}
