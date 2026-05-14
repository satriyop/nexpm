<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { AlertCircle, Bot, Loader2, Send, Sparkles } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

import { store as storeAiMessage } from '@/actions/App/Http/Controllers/Admin/AiAssistantController';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

type ChatMessage = {
    id?: number;
    role: 'user' | 'assistant';
    content: string;
    tool_name?: string | null;
};

type AiResponse = {
    conversation_id: number;
    message: ChatMessage;
};

const page = usePage();
const open = ref(false);
const input = ref('');
const processing = ref(false);
const error = ref<string | null>(null);
const conversationId = ref<number | null>(null);
const messages = ref<ChatMessage[]>([]);
const messagesEl = ref<HTMLElement | null>(null);

const isSuperAdmin = computed(() => page.props.auth.user.role === 'super_admin');

const quickPrompts = [
    'Find blocked assignments',
    'Summarize dashboard progress',
    'Check report readiness',
];

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const scrollToBottom = async () => {
    await nextTick();

    if (messagesEl.value) {
        messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }
};

const ask = async (prompt?: string) => {
    const message = (prompt ?? input.value).trim();

    if (!message || processing.value) {
        return;
    }

    input.value = '';
    error.value = null;
    processing.value = true;
    messages.value.push({ role: 'user', content: message });
    await scrollToBottom();

    try {
        const response = await fetch(storeAiMessage.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                message,
                conversation_id: conversationId.value,
                context: {
                    type: 'page',
                    label: page.url,
                },
            }),
        });

        const payload = (await response.json()) as Partial<AiResponse> & { message?: ChatMessage | string };

        if (!response.ok) {
            throw new Error(typeof payload.message === 'string' ? payload.message : 'AI assistant request failed.');
        }

        conversationId.value = payload.conversation_id ?? conversationId.value;

        if (payload.message && typeof payload.message !== 'string') {
            messages.value.push(payload.message);
        }
    } catch (requestError) {
        error.value = requestError instanceof Error ? requestError.message : 'AI assistant request failed.';
    } finally {
        processing.value = false;
        await scrollToBottom();
    }
};
</script>

<template>
    <Sheet v-if="isSuperAdmin" v-model:open="open">
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger as-child>
                    <SheetTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Open AI assistant">
                            <Sparkles class="size-4" />
                        </Button>
                    </SheetTrigger>
                </TooltipTrigger>
                <TooltipContent side="bottom">AI Assistant</TooltipContent>
            </Tooltip>
        </TooltipProvider>

        <SheetContent side="right" class="w-full gap-0 p-0 sm:max-w-[520px]">
            <SheetHeader class="border-b px-5 py-4">
                <SheetTitle class="flex items-center gap-2 text-base">
                    <Bot class="size-4" />
                    AI Assistant
                </SheetTitle>
            </SheetHeader>

            <div ref="messagesEl" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                <div v-if="messages.length === 0" class="grid gap-2">
                    <button
                        v-for="prompt in quickPrompts"
                        :key="prompt"
                        type="button"
                        class="rounded-md border border-border px-3 py-2 text-left text-sm transition hover:bg-accent hover:text-accent-foreground"
                        @click="ask(prompt)"
                    >
                        {{ prompt }}
                    </button>
                </div>

                <div
                    v-for="(message, index) in messages"
                    :key="message.id ?? `${message.role}-${index}`"
                    class="flex"
                    :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[86%] rounded-md px-3 py-2 text-sm leading-6 whitespace-pre-wrap"
                        :class="
                            message.role === 'user'
                                ? 'bg-primary text-primary-foreground'
                                : 'border border-border bg-muted/40 text-foreground'
                        "
                    >
                        <div v-if="message.tool_name" class="mb-1 text-xs font-medium text-muted-foreground">
                            {{ message.tool_name.replaceAll('_', ' ') }}
                        </div>
                        {{ message.content }}
                    </div>
                </div>

                <div v-if="processing" class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 class="size-4 animate-spin" />
                    Thinking
                </div>

                <div v-if="error" class="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <span>{{ error }}</span>
                </div>
            </div>

            <form class="flex gap-2 border-t p-4" @submit.prevent="ask()">
                <textarea
                    v-model="input"
                    rows="2"
                    class="min-h-11 flex-1 resize-none rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="processing"
                    placeholder="Ask about assignments, reports, or progress"
                    @keydown.enter.exact.prevent="ask()"
                />
                <Button type="submit" size="icon" :disabled="processing || input.trim() === ''" aria-label="Send message">
                    <Loader2 v-if="processing" class="size-4 animate-spin" />
                    <Send v-else class="size-4" />
                </Button>
            </form>
        </SheetContent>
    </Sheet>
</template>
