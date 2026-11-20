import type {Assistant} from '$plugins/assistants/types/assistant/Assistant';

/**
 * The assistant currently open in a dashboard list's detail modal: the modal
 * writes it (AssistantDetailModal), the page's breadcrumb bar reads it as its
 * leaf crumb (DashboardHeader). Module-scope reactive state like the admin
 * flow's `currentReview`; null while no detail is open.
 */
export const openDetail = $state<{assistant: Assistant | null}>({assistant: null});
