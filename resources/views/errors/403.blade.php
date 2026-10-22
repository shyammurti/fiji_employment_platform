<x-error-page code="403" icon="lock" title="You do not have access" :message="($exception?->getMessage() ?: null) ?? 'Your account does not have permission to view this page.'" />
