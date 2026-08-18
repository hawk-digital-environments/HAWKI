import type { PageLoad } from './$types';
import { error } from '@sveltejs/kit';
import { ApiError } from '$lib/data/api';
import { getAssistant, assistantDependency } from '$lib/data/api/resources/assistant/assistantsClient';
import {getAssistantFeedbacks} from "$lib/data/api/resources/assistant/AssistantFeedbackClient";
import type { AssistantFeedback } from '$lib/plugins/assistants/types/assistant/AssistantFeedback';
import { useMockData, getMockAssistant, getMockFeedback } from '$lib/data/api/mock/mockData';

