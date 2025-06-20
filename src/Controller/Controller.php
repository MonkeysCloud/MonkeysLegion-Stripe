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
    public function executeWithTimeout(callable $callback, int $timeout): mixed
    {
        $result = null;
        $completed = false;

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException('Failed to fork process for timeout handling.');
        } elseif ($pid === 0) {
            // Child process: execute the callback
            $result = $callback();
            posix_kill(posix_getppid(), SIGUSR1); // Notify parent process of completion
            exit(0);
        } else {
            // Parent process: wait for child or timeout
            pcntl_signal(SIGUSR1, function () use (&$completed) {
                $completed = true;
            });

            $startTime = time();
            while (!$completed && (time() - $startTime) < $timeout) {
                pcntl_signal_dispatch();
                usleep(100000); // Sleep for 100ms
            }

            if (!$completed) {
                posix_kill($pid, SIGKILL); // Kill the child process
                throw new \RuntimeException('Timeout reached while processing webhook.');
            }

            pcntl_wait($status); // Wait for child process to exit
        }

        return $result;
    }
}
