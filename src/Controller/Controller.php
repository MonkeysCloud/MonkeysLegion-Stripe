<?php

namespace MonkeysLegion\Stripe\Controller;

class Controller
{
    /**
     * Execute a callable with a timeout.
     *
     * @param callable $callback The function to execute
     * @param int $timeout Timeout in seconds
     * @return mixed The result of the callable
     * @throws \RuntimeException If the timeout is reached
     */
    public function executeWithTimeout(callable $callback, int $timeout)
    {
        if ($timeout <= 0) {
            throw new \RuntimeException('Timeout must be > 0');
        }

        // If PCNTL is not available, just execute without timeout
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid') || !function_exists('posix_kill')) {
            return $callback();
        }

        // Skip forking on Windows
        if (PHP_OS_FAMILY === 'Windows') {
            return $callback();
        }

        return $this->executeWithFork($callback, $timeout);
    }

    /**
     * Execute with fork-based timeout
     */
    private function executeWithFork(callable $callback, int $timeout)
    {
        // Create temporary file for IPC
        $tempFile = tempnam(sys_get_temp_dir(), 'timeout_exec_');
        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary file');
        }

        try {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new \RuntimeException('Failed to fork process');
            }

            if ($pid === 0) {
                // Child process - execute the callback and write result to temp file
                try {
                    $result = $callback();
                    $data = ['success' => true, 'result' => $result];
                } catch (\Throwable $e) {
                    $data = [
                        'success' => false,
                        'exception' => [
                            'class' => get_class($e),
                            'message' => $e->getMessage(),
                            'code' => $e->getCode()
                        ]
                    ];
                }

                file_put_contents($tempFile, serialize($data), LOCK_EX);
                exit($data['success'] ? 0 : 1);
            }

            // Parent process - wait for child with timeout
            $start = time();

            while ((time() - $start) < $timeout) {
                $status = null;
                $result = pcntl_waitpid($pid, $status, WNOHANG);

                if ($result === $pid) {
                    // Child finished - read result
                    return $this->readChildResult($tempFile, $status);
                } elseif ($result === 0) {
                    // Child still running
                    usleep(100000); // 100ms
                    continue;
                } else {
                    // Error occurred
                    throw new \RuntimeException('Error waiting for child process');
                }
            }

            // Timeout reached - kill child
            $this->killChild($pid);
            throw new \RuntimeException('Timeout reached while processing');
        } finally {
            // Cleanup temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Read result from child process
     */
    private function readChildResult(string $tempFile, int $status)
    {
        if (!file_exists($tempFile) || filesize($tempFile) === 0) {
            throw new \RuntimeException('Child process terminated without writing result');
        }

        $content = file_get_contents($tempFile);
        if ($content === false) {
            throw new \RuntimeException('Failed to read result from child process');
        }

        $data = unserialize($content);
        if ($data === false || !is_array($data) || !isset($data['success'])) {
            throw new \RuntimeException('Invalid result format from child process');
        }

        if ($data['success']) {
            return $data['result'];
        } else {
            $ex = $data['exception'];
            $class = $ex['class'];
            if (class_exists($class)) {
                throw new $class($ex['message'], $ex['code']);
            } else {
                throw new \RuntimeException($ex['message'], $ex['code']);
            }
        }
    }

    /**
     * Kill child process
     */
    private function killChild(int $pid): void
    {
        // Send SIGTERM first
        if (posix_kill($pid, SIGTERM)) {
            // Give process 200ms to terminate gracefully
            usleep(200000);

            // If still alive, force kill
            if (posix_kill($pid, 0)) {
                posix_kill($pid, SIGKILL);
            }
        }

        // Clean up zombie process
        $status = null;
        pcntl_waitpid($pid, $status, WNOHANG);
    }
}
