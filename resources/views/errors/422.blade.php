<x-error-page code="422" title="That action is not available" :message="($exception?->getMessage() ?: null) ?? 'The request could not be completed in its current state.'" />
