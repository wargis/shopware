<?php declare(strict_types=1);

namespace Shopware\Unit\Event\UnitTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Unit\Collection\UnitTranslationDetailCollection;
use Shopware\Unit\Event\Unit\UnitBasicLoadedEvent;

class UnitTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'unit_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var UnitTranslationDetailCollection
     */
    protected $unitTranslations;

    public function __construct(UnitTranslationDetailCollection $unitTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->unitTranslations = $unitTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getUnitTranslations(): UnitTranslationDetailCollection
    {
        return $this->unitTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->unitTranslations->getUnits()->count() > 0) {
            $events[] = new UnitBasicLoadedEvent($this->unitTranslations->getUnits(), $this->context);
        }
        if ($this->unitTranslations->getLanguages()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->unitTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}