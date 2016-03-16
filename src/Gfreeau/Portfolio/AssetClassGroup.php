<?php

namespace Gfreeau\Portfolio;

class AssetClassGroup
{
    protected $assetClasses;

    /**
     * @param array $assetClasses
     */
    public function __construct(array $assetClasses)
    {
        $this->assetClasses = [];

        $existingAssetClasses = [];
        $totalAssetClassPercentage = 0;

        foreach($assetClasses as &$assetClass) {
            if (in_array($assetClass['assetClass'], $existingAssetClasses)) {
                throw new \InvalidArgumentException("duplicate asset class detected");
            }

            if (!array_key_exists('assetClass', $assetClass)) {
                throw new \InvalidArgumentException("assetClass key is not present");
            }

            if (!array_key_exists('percentage', $assetClass)) {
                throw new \InvalidArgumentException("percentage key is not present");
            }

            $assetClass['percentage'] = (double) $assetClass['percentage'];

            $this->assetClasses[] = $assetClass;

            $existingAssetClasses[] = $assetClass['assetClass'];
            $totalAssetClassPercentage += $assetClass['percentage'];
        }

        if ($totalAssetClassPercentage > 1) {
            throw new \InvalidArgumentException("Asset class percentage cannot be over 100%");
        }

        $this->assetClasses = $assetClasses;
    }

    public function getPercentage(AssetClass $assetClass): float
    {
        $assetClassKey = array_search($assetClass, array_column($this->assetClasses, 'assetClass'));

        if ($assetClassKey === false) {
            return 0;
        }

        return $this->assetClasses[$assetClassKey]['percentage'];
    }

    public function has(AssetClass $assetClass): bool
    {
        return false !== array_search($assetClass, array_column($this->assetClasses, 'assetClass'));
    }
}