<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Video;
use App\Mapper\CustomUuidMapper;
use App\Service\UserService;
use App\Service\VideoLinkService;
use App\Service\VideoService;
use Doctrine\DBAL\Types\ConversionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class WatchController extends AbstractController
{
    private const NOT_ALLOWED = 0;
    private const ALLOWED = 1;
    private const IS_OWNER = 2;

    public const OWNER_LINK_ID = "owner";
    public const CONTENT_DIRECTORY = "../content/";

    private const PLAYLIST_MIME_TYPE = "application/x-mpegURL";
    private const TS_FILE_MIME_TYPE = "video/MP2T";
    private const THUMBNAIL_MIME_TYPE = "image/png";

    private const TS_FILE_FORMAT = "seg-%06d-ts";

    private $userService;
    private $videoService;
    private $videoLinkService;
    private $uuidMapper;

    public function __construct(
        UserService $userService,
        VideoService $videoService,
        VideoLinkService $videoLinkService,
        CustomUuidMapper $uuidMapper
    )
    {
        $this->userService = $userService;
        $this->videoService = $videoService;
        $this->videoLinkService = $videoLinkService;
        $this->uuidMapper = $uuidMapper;
    }

    private function isAllowed(?Video $video, ?User $user, $linkId): int
    {
        if ($video->getUploader() == $user) {
            return self::IS_OWNER;
        }

        $link = $this->videoLinkService->get($this->uuidMapper->fromString($linkId));
        if (!$link) {
            return self::NOT_ALLOWED;
        }

        if ($link->getVideo() != $video) {
            return self::NOT_ALLOWED;
        }

        // TODO: check constraints

        return self::ALLOWED;
    }

    private function checkRequestData($videoId, $linkId): array
    {
        try {
            $video = $this->videoService->get($this->uuidMapper->fromString($videoId));
            $user = $this->userService->getLoggedInUser();

            $allowed = $this->isAllowed($video, $user, $linkId);
        } catch (ConversionException $e) {
            throw new AccessDeniedHttpException();
        }

        if (!$allowed) {
            throw new AccessDeniedHttpException();
        }

        return [
            "video" => $video,
            "user" => $user,
            "isOwner" => $allowed == self::IS_OWNER
        ];
    }

    /**
     * @Route("/{linkId}/{videoId}/playlist", name="app_watch_global")
     */
    public function globalPlaylist($videoId, $linkId): Response
    {
        $data = $this->checkRequestData($videoId, $linkId);

        $file = self::CONTENT_DIRECTORY . $data["video"]->getId() . "/" . "playlist.m3u8";

        $response = new BinaryFileResponse($file);
        $response->headers->set("Content-Type", self::PLAYLIST_MIME_TYPE);

        return $response;
    }

    /**
     * @Route("/{linkId}/{videoId}/{quality}/playlist", name="app_watch_quality", requirements={"quality"="360|480|720|1080"})
     */
    public function qualityPlaylist($videoId, $linkId, int $quality): Response
    {
        $data = $this->checkRequestData($videoId, $linkId);

        $file = self::CONTENT_DIRECTORY . $data["video"]->getId() . "/" . $quality . "p/" . "playlist.m3u8";

        $response = new BinaryFileResponse($file);
        $response->headers->set("Content-Type", self::PLAYLIST_MIME_TYPE);

        return $response;
    }

    /**
     * @Route("/{linkId}/{videoId}/{quality}/seg-{tsFileId}-ts", name="app_watch_segment", requirements={"quality"="360|480|720|1080", "tsFileId"="\d+"})
     */
    public function tsFile($videoId, $linkId, int $quality, int $tsFileId): Response
    {
        $data = $this->checkRequestData($videoId, $linkId);

        $file = self::CONTENT_DIRECTORY . $data["video"]->getId() . "/" . $quality . "p/" . sprintf(self::TS_FILE_FORMAT, $tsFileId);

        $response = new BinaryFileResponse($file);
        $response->headers->set("Content-Type", self::TS_FILE_MIME_TYPE);

        return $response;
    }

    /**
     * @Route("/{linkId}/{videoId}/thumb", name="app_watch_thumbnail")
     */
    public function thumbnail($videoId, $linkId): Response
    {
        $data = $this->checkRequestData($videoId, $linkId);

        $file = self::CONTENT_DIRECTORY . $data["video"]->getId() . "/" . "thumb.png";

        $response = new BinaryFileResponse($file);
        $response->headers->set("Content-Type", self::THUMBNAIL_MIME_TYPE);

        return $response;
    }

    /**
     * @Route("/{linkId}/{videoId}/", name="app_watch_page")
     */
    public function watchPage($videoId, $linkId): Response
    {
        $data = $this->checkRequestData($videoId, $linkId);

        return $this->render("watch/watch.html.twig", [
            "thumbnail" => $this->generateUrl("app_watch_thumbnail", [
                "linkId" => $linkId,
                "videoId" => $videoId
            ]),
            "global" => $this->generateUrl("app_watch_global", [
                "linkId" => $linkId,
                "videoId" => $videoId
            ]),
            "video" => $data["video"],
        ]);
    }
}