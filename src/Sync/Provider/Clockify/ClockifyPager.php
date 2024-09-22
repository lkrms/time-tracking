<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Provider\Clockify;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerPageInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Curler\Pager\HasEntitySelector;
use Salient\Curler\CurlerPage;
use Closure;

final class ClockifyPager implements CurlerPagerInterface
{
    use HasEntitySelector;

    /**
     * Creates a new ClockifyPager object
     *
     * @param (Closure(mixed): list<mixed>)|array-key|null $entitySelector
     */
    public function __construct($entitySelector = null)
    {
        $this->applyEntitySelector($entitySelector);
    }

    /**
     * @inheritDoc
     */
    public function getFirstRequest(
        RequestInterface $request,
        CurlerInterface $curler,
        ?array $query = null
    ): RequestInterface {
        return $request;
    }

    /**
     * @inheritDoc
     */
    public function getPage(
        $data,
        RequestInterface $request,
        HttpResponseInterface $response,
        CurlerInterface $curler,
        ?CurlerPageInterface $previousPage = null,
        ?array $query = null
    ): CurlerPageInterface {
        $data = ($this->EntitySelector)($data);

        return new CurlerPage($data);
    }
}
