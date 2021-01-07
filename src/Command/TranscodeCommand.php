<?php

namespace App\Command;

use App\Service\TranscodingService;
use App\Service\VideoService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class TranscodeCommand extends Command
{
    protected static $defaultName = "app:start-transcode";

    private $videoService;
    private $transcodingService;

    public function __construct(
        VideoService $videoService,
        TranscodingService $transcodingService,
        string $name = null
    )
    {
        parent::__construct($name);
        $this->videoService = $videoService;
        $this->transcodingService = $transcodingService;
    }

    protected function configure()
    {
        $this->setDescription("starts transcode process");
    }

    private function callScript($name, $_arguments): bool
    {
        $arguments = ["./scripts/" . $name];
        $arguments = array_merge($arguments, $_arguments);

        $process = new Process($arguments);
        $process->setWorkingDirectory("./");
        $process->setTimeout(null);
        $process->run();

        return $process->isSuccessful();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        while (true) {
            sleep(1);

            $videos = $this->videoService->getVideosForTranscode();
            foreach ($videos as $video) {
                $output->writeln("new: " . $video->getName() . ", " . $video->getUploader()->getName());

                $this->transcodingService->doTranscode($video, $output);

                $output->writeln("done");
            }
        }
    }
}