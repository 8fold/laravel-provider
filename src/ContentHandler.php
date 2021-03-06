<?php

namespace Eightfold\Site;

use Carbon\Carbon;

use Eightfold\ShoopShelf\Shoop;
use Eightfold\ShoopShelf\FluentTypes\ESStore;

class ContentHandler
{
    /**
     * Title member from YAML front matter.
     */
    public const TITLE = "title";

    /**
     * Heading member from YAML front matter, falls back to title member,
     * if heading not set.
     */
    public const HEADING = "heading";

    /**
     * Recursively uses title member from YAML front matter to build a fully-
     * qualified title string with separator. ex. Leaf | Branch | Trunk | Root
     */
    public const PAGE = "page";

    /**
     * @deprecated
     *
     * Uses the title member from YAML front matter to build a two-part title,
     * which includes the title for the current URL plus the title of the root
     * page with a separater. ex. Leaf | Root
     */
    public const SHARE = "share";

    /**
     * Uses the title member from YAML front matter to build a two-part title,
     * which includes the title for the current URL plus the title of the root
     * page with a separater. ex. Leaf | Root
     */
    public const BOOKEND = "book-end";

    private $useLocal = true;
    private $localRootPath;
    private $remoteRootPath = "";
    private $githubClient = null;

    static public function fold(ESStore $localRootPath, ESStore $remoteRootPath = null)
    {
        return new static($localRootPath, $remoteRootPath);
    }

    static public function uri($parts = false) // :ESString|ESArray
    {
        $base = Shoop::url(request()->url())->path(false)->start("/")->isEmpty(function($result, $path) {
            return ($result->unfold()) ? Shoop::string("/") : $path;
        });
        return ($parts) ? $base->divide("/")->noEmpties()->reindex() : $base;
    }

    static public function rootUri(): ESString
    {
        return static::uri(true)->isEmpty(function($result, $array) {
            return ($result->unfold()) ? Shoop::string("") : $array->first();
        });
    }

    public function __construct(ESStore $localRootPath, ESStore $remoteRootPath = null)
    {
        $this->localRootPath = $localRootPath;

        if ($this->remoteRootPath !== null) {
            $this->remoteRootPath = $remoteRootPath;
            $ghToken = env("GITHUB_PERSONAL_TOKEN");
            $ghUsername = env("GITHUB_USERNAME");
            $ghRepo = env("GITHUB_REPO");
            if (
                $ghToken !== null and
                $ghUsername !== null and
                $ghRepo !== null and
                $remoteRootPath !== null
            )
            {
                $this->useLocal = false;
                $this->githubClient = Shoop::github(
                    $remoteRootPath,
                    $ghToken,
                    $ghUsername,
                    $ghRepo,
                    $localRootPath,
                    ".cache"
                );
            }

        } else {
            $this->remoteRootPath = Shoop::path("");

        }
    }

    public function useLocal()
    {
        return $this->useLocal;
    }

    public function localRoot(): ESStore
    {
        return $this->localRootPath;
    }

    public function remoteRoot(): ESStore
    {
        return $this->remoteRoot;
    }

    public function githubClient()
    {
        return $this->githubClient;
    }

    public function store(bool $useRoot = false, ...$plus): ESStore
    {
        $store = ($this->useLocal())
            ? $this->localRoot()
            : $this->githubClient();

        if (! $useRoot) {
            $parts = Shoop::store(request()->path())->parts();
            if ($parts->isEmpty()->reversed()->unfold()) {
                $store = $store->append($parts->unfold());
            }
        }

        if ($useRoot or count($plus) > 0) {
            if (Shoop::array($plus)->countIsGreaterThanUnfolded(0)) {
                $store = $store->plus(...$plus);
            }
        }
        return $store;
    }

    public function contentStore(bool $useRoot = false, ...$plus): ESStore // ESStore|ESGitHubClient
    {
        return $this->store($useRoot, ...$plus)->plus("content.md");
    }

    public function assetsStore(...$plus): ESStore
    {
        return $this->store(true, ".assets")->append($plus);
    }

    public function mediaStore(...$plus): ESStore
    {
        return static::store(true, ".media")->append($plus);
    }

    public function trackerStore(...$plus): ESStore
    {
        return static::store(true, ".tracker")->append($plus);
    }

    public function eventStore(...$plus): ESStore
    {
        return static::store(true, "events")->append($plus);
    }

    public function title($type = "", $checkHeadingFirst = true, $parts = []): ESString
    {
        if (strlen($type) === 0) {
            $type = static::PAGE;
            $checkHeadingFirst = false;
        }

        $parts = Type::sanitizeType($parts, ESArray::class);
        if ($parts->isEmpty) {
            $parts = static::uri(true);
        }

        $titles = Shoop::array([]);
        if ($checkHeadingFirst and
            Shoop::string(static::HEADING)->isUnfolded($type)
        ) {
            $titles = $titles->append(
                $this->titles($checkHeadingFirst, $parts)->first()
            );

        } elseif (Shoop::string(static::TITLE)->isUnfolded($type)) {
            $titles = $titles->append(
                $this->titles(false, $parts)->first()
            );

        } elseif (Shoop::string(static::BOOKEND)->isUnfolded($type)) {
            if ($this->uri(true)->isEmpty) {
                $titles = $titles->append(
                    $this->titles($checkHeadingFirst, $parts)->first()
                );

            } else {
                $t = $this->titles($checkHeadingFirst, $parts)->divide(-1);
                $start = $t->first()->first();
                $root = $t->last()->first();
                if ($this->uri(true)->isUnfolded("events")) {
                    $eventTitles = $this->eventsTitles();
                    $start = $start->start($eventTitles->month ." ". $eventTitles->year);
                    $root = $this->contentStore(true)->markdown()->meta()->title();
                }

                $titles = $titles->append($start, $root);
            }

        } elseif (Shoop::string(static::PAGE)->isUnfolded($type)) {
            $t = $this->titles(false, $parts)->divide(-1);
            $start = $t->first();
            $root = $t->last();
            if ($this->uri(true)->isUnfolded("events")) {
                die("here");
                $eventTitles = $this->eventsTitles(
                    $type = "",
                    $checkHeadingFirst = true,
                    $parts = []
                );
                $start = $start->start($eventTitles->month, $eventTitles->year);
            }
            $titles = $titles->append($start)->append($root);

        }
        return $titles->noEmpties()->join(" | ");
    }

    public function titles($checkHeadingFirst = true, $parts = []): ESArray
    {
        $parts = Type::sanitizeType($parts, ESArray::class);

        // $useRoot = $parts->countIsLessThanUnfolded(1);

        $store = $this->store(true, ...$parts);

        return $parts->each(function($part) use (&$store, $checkHeadingFirst) {
            $s = $store->append(["content.md"]);
            $title = (! $checkHeadingFirst)
                ? $s->metaMember("title")
                : $s->metaMember("heading")->countIsGreaterThan(0,
                    function($result, $title) use ($s) {
                        return ($result->unfold())
                            ? $title
                            : $s->metaMember("title");
                    });

            if ($store->parts()->countIsGreaterThanUnfolded(0)) {
                $store = $store->dropLast();
            }
            return $title;

        })->noEmpties()->append(
            (! $checkHeadingFirst)
                ? $this->contentStore(true)->metaMember("title")
                : $this->contentStore(true)->metaMember("heading")
                    ->countIsGreaterThan(0, function($result, $title) {
                        return ($result->unfold())
                            ? $title
                            : $this->contentStore(true)->metaMember("title");
                    })
        );
    }

   public function eventsTitles($checkHeadingFirst = true, $parts = [])
    {
        $parts = static::uri();
        $year  = $parts->dropFirst()->first;
        $month = $this->dateString($parts->dropFirst(2)->first, "m", "F");

        return Shoop::dictionary([
            "year"  => $year,
            "month" => $month
        ]);
    }

    public function details()
    {
        $meta = $this->contentStore()->markdown()->meta();

        $return = Shoop::dictionary([]);
        $return = $return->plus(
            ($meta->created === null)
                ? Shoop::string("")
                : $this->dateString($meta->created),
            "created"
        );

        $return = $return->plus(
            ($meta->modified === null)
                ? Shoop::string("")
                : $this->dateString($meta->modified),
            "modified"
        );

        $return = $return->plus(
            ($meta->moved === null)
                ? Shoop::string("")
                : $this->dateString($meta->moved),
            "moved"
        );

        $return = $return->plus(
            ($meta->original === null)
                ? Shoop::string("")
                : Shoop::string($meta->original),
            "original"
        );

        return $return->noEmpties();
    }

    public function copyright($name, $startYear = ""): ESString
    {
        if (strlen($startYear) > 0) {
            $startYear = $startYear ."&ndash;";
        }
        return Shoop::string("Copyright © {$startYear}". date("Y") ." {$name}. All rights reserved.");
    }

    private function dateString(
        string $yyyymmdd,
        string $startFormat = "Ymd",
        string $endFormat = ""
    ): ESString
    {
        if (empty($endFormat)) {
            return Shoop::string(
                Carbon::createFromFormat("Ymd", $yyyymmdd, "America/Chicago")
                    ->toFormattedDateString()
            );
        }
        return Shoop::string(
            Carbon::createFromFormat($startFormat, $yyyymmdd, "America/Chicago")
                ->format($endFormat)
        );
    }

    public function description(bool $useRoot = false, ...$plus): ESString
    {
        $description = $this->contentStore($useRoot, ...$plus)
            ->markdown()->meta()->description;
        $description = ($description === null)
            ? Shoop::string("")
            : Shoop::string($description);

        return $description->isNotEmpty(function($result, $description) {
            if ($result->unfold()) {
                return Shoop::string($description);
            }
            return $this->descriptionImmediateFallback()->isNotEmpty(
                function($result, $description) {
                    return ($result->unfold())
                        ? $description
                        : $this->title(static::BOOKEND);
            });
        });
    }

    public function descriptionImmediateFallback(): ESString
    {
        return Shoop::string("");
    }

    public function socialImage(): ESString
    {
        $parts = $this->uri(true);
        $store = $this->mediaStore()->plus(...$parts);
        return $parts->each(function($part, $index, &$break) use (&$store) {
            $poster = $store->plus("poster.png");
            $posterAlt = $store->plus("poster.jpg");
            if ($poster->isFile) {
                $break = true;
                return Shoop::string($store)->minus($this->mediaStore())
                        ->start(request()->root(), "/media")->plus("/poster.png");

            } elseif ($posterAlt->isFile) {
                return Shoop::string($store)->minus($this->mediaStore())
                        ->start(request()->root(), "/media")->plus("/poster.jpg");

            } else {
                $store = $store->dropLast();
                return "";

            }
        })->noEmpties()->isEmpty(function($result, $array) use (&$store) {
            return $store->plus("poster.png")->isFile(
                function($result, $store) {
                    return ($result->unfold())
                        ? Shoop::string(request()->root())
                            ->plus("/media/poster.png")
                        : $store->dropLast()->plus("poster.jpg")->isFile(
                            function($result, $store) {
                                return ($result->unfold())
                                    ? Shoop::string(request()->root())
                                        ->plus("/media/poster.jpg")
                                    : Shoop::string("");
                    });
                });
            });
        // });
    }
}
