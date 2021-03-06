<?php

final class PhrictionDocumentPolicyCodex
  extends PhabricatorPolicyCodex {

  public function getPolicySpecialRuleDescriptions() {
    $object = $this->getObject();
    $strongest_policy = $this->getStrongestPolicy();

    $rules = array();
    $rules[] = $this->newRule()
      ->setDescription(
        pht('To view a wiki document, you must also be able to view all '.
            'of its ancestors. The most-restrictive view policy of this '.
            'document\'s ancestors is "%s".',
            $strongest_policy->getShortName()))
      ->setCapabilities(array(PhabricatorPolicyCapability::CAN_VIEW));

    $rules[] = $this->newRule()
      ->setDescription(
        pht('To edit a wiki document, you must also be able to view all '.
            'of its ancestors.'))
      ->setCapabilities(array(PhabricatorPolicyCapability::CAN_EDIT));

    return $rules;
  }

  public function getDefaultPolicy() {
    $ancestors = $this->getObject()->getAncestors();
    if ($ancestors) {
      $root = head($ancestors);
    } else {
      $root = $this->getObject();
    }

    $root_policy_phid = $root->getPolicy($this->getCapability());

    return id(new PhabricatorPolicyQuery())
            ->setViewer($this->getViewer())
            ->withPHIDs(array($root_policy_phid))
            ->executeOne();
  }

  public function compareToDefaultPolicy(PhabricatorPolicy $policy) {
    $root_policy = $this->getDefaultPolicy();
    $strongest_policy = $this->getStrongestPolicy();

    // Note that we never return 'weaker', because Phriction documents can
    // never have weaker permissions than their parents. If this object has
    // been set to weaker permissions anyway, return 'adjusted'.
    if ($root_policy == $strongest_policy) {
      $strength = null;
    } else if ($strongest_policy->isStrongerThan($root_policy)) {
      $strength = PhabricatorPolicyStrengthConstants::STRONGER;
    } else {
      $strength = PhabricatorPolicyStrengthConstants::ADJUSTED;
    }
    return $strength;
  }

  private function getStrongestPolicy() {
    $ancestors = $this->getObject()->getAncestors();
    $ancestors[] = $this->getObject();

    $strongest_policy = $this->getDefaultPolicy();
    foreach ($ancestors as $ancestor) {
      $ancestor_policy_phid = $ancestor->getPolicy($this->getCapability());

      $ancestor_policy = id(new PhabricatorPolicyQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs(array($ancestor_policy_phid))
        ->executeOne();

      if ($ancestor_policy->isStrongerThan($strongest_policy)) {
        $strongest_policy = $ancestor_policy;
      }
    }

    return $strongest_policy;
  }

}
