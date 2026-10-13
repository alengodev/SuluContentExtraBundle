<?php

declare(strict_types=1);

namespace Alengo\SuluContentExtraBundle\RequestWorkflow;

use Alengo\SuluContentExtraBundle\Model\AdditionalDataInterface;
use Sulu\Content\Application\RequestWorkflow\PreValidator\PreValidationContext;
use Sulu\Content\Application\RequestWorkflow\PreValidator\RequestWorkflowPreValidatorInterface;
use Sulu\Content\Application\RequestWorkflow\Validator\ValidationResult;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowTransitionRequest\WorkflowTransitionRequestDecisionMessage;

/**
 * Review-workflow gate (Sulu 3.1+) that refuses "request for review" and "publish" while
 * configured additional data fields are empty — the counterpart of Sulu's `seo_required`
 * and `excerpt_required` for the Additional Data tab:
 *
 *     sulu_content:
 *         request_workflows:
 *             default:
 *                 pre_validators:
 *                     additional_data_required: { fields: [template_theme, notes] }
 *
 * The workflow runs on the localized dimension content, while unlocalized keys live on the
 * unlocalized one, so both are read. A field counts as missing when it is null, an empty or
 * whitespace-only string or an empty array; `false` and `0` are values.
 *
 * Only registered when Sulu ships request workflows, see the extension.
 */
final class AdditionalDataRequiredPreValidator implements RequestWorkflowPreValidatorInterface
{
    public const KEY = 'additional_data_required';

    public const MISSING_MESSAGE = 'alengo_content_extra.workflow_transition_request.additional_data_required.missing';

    public static function getKey(): string
    {
        return self::KEY;
    }

    public function check(PreValidationContext $context): ValidationResult
    {
        $dimensionContent = $context->dimensionContent;
        if (!$dimensionContent instanceof AdditionalDataInterface) {
            return ValidationResult::approve();
        }

        $data = [
            ...$this->findUnlocalizedAdditionalData($dimensionContent),
            ...$dimensionContent->getAdditionalData(),
        ];

        $missing = [];
        foreach ($this->resolveFields($context->config) as $field) {
            if ($this->isEmpty($data[$field] ?? null)) {
                $missing[] = $field;
            }
        }

        if ([] === $missing) {
            return ValidationResult::approve();
        }

        return ValidationResult::reject(WorkflowTransitionRequestDecisionMessage::translated(
            self::MISSING_MESSAGE,
            ['fields' => \implode(', ', $missing)],
        ));
    }

    /**
     * @template T of \Sulu\Content\Domain\Model\ContentRichEntityInterface
     *
     * @param DimensionContentInterface<T> $dimensionContent
     *
     * @return array<string, mixed>
     */
    private function findUnlocalizedAdditionalData(DimensionContentInterface $dimensionContent): array
    {
        if (null === $dimensionContent->getLocale()) {
            return []; // already the unlocalized one
        }

        foreach ($dimensionContent->getResource()->getDimensionContents() as $candidate) {
            if (null === $candidate->getLocale()
                && $candidate->getStage() === $dimensionContent->getStage()
                && DimensionContentInterface::CURRENT_VERSION === $candidate->getVersion()
                && $candidate instanceof AdditionalDataInterface
            ) {
                return $candidate->getAdditionalData();
            }
        }

        return [];
    }

    /**
     * The config tree passes the block through unvalidated; a misconfiguration must fail
     * loudly instead of approving everything.
     *
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private function resolveFields(array $config): array
    {
        $fields = $config['fields'] ?? null;
        if (!\is_array($fields) || [] === $fields) {
            throw new \LogicException(\sprintf(
                'The "%s" pre-validator needs a non-empty list of fields, got %s.',
                self::KEY,
                \get_debug_type($fields),
            ));
        }

        $list = [];
        foreach ($fields as $field) {
            if (!\is_string($field) || '' === $field) {
                throw new \LogicException(\sprintf(
                    'The "%s" pre-validator needs field names, got %s.',
                    self::KEY,
                    \get_debug_type($field),
                ));
            }

            $list[] = $field;
        }

        return $list;
    }

    private function isEmpty(mixed $value): bool
    {
        return null === $value
            || (\is_string($value) && '' === \trim($value))
            || [] === $value;
    }
}
