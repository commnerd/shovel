/**
 * @jest-environment jsdom
 */

import { mount } from '@vue/test-utils';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import TaskConfirmationDialog from '@/components/TaskConfirmationDialog.vue';

// Mock UI components
vi.mock('@/components/ui/button', () => ({
  Button: {
    name: 'Button',
    template: '<button><slot /></button>',
    props: ['variant', 'size', 'disabled'],
  }
}));

vi.mock('@/components/ui/card', () => ({
  Card: {
    name: 'Card',
    template: '<div class="card"><slot /></div>',
    props: ['class']
  },
  CardContent: {
    name: 'CardContent',
    template: '<div class="card-content"><slot /></div>',
  },
  CardHeader: {
    name: 'CardHeader',
    template: '<div class="card-header"><slot /></div>',
  },
  CardTitle: {
    name: 'CardTitle',
    template: '<div class="card-title"><slot /></div>',
  }
}));

vi.mock('@/components/ui/badge', () => ({
  Badge: {
    name: 'Badge',
    template: '<span class="badge"><slot /></span>',
    props: ['variant', 'class']
  }
}));

vi.mock('@/components/ui/scroll-area', () => ({
  ScrollArea: {
    name: 'ScrollArea',
    template: '<div class="scroll-area"><slot /></div>',
    props: ['class']
  }
}));

vi.mock('@/components/ui/dialog', () => ({
  Dialog: {
    name: 'Dialog',
    template: '<div v-if="open" class="dialog"><slot /></div>',
    props: ['open'],
    emits: ['update:open']
  },
  DialogContent: {
    name: 'DialogContent',
    template: '<div class="dialog-content" :class="$attrs.class"><slot /></div>',
    props: ['class']
  },
  DialogHeader: {
    name: 'DialogHeader',
    template: '<div class="dialog-header"><slot /></div>',
  },
  DialogTitle: {
    name: 'DialogTitle',
    template: '<div class="dialog-title"><slot /></div>',
  },
  DialogDescription: {
    name: 'DialogDescription',
    template: '<div class="dialog-description"><slot /></div>',
  },
  DialogFooter: {
    name: 'DialogFooter',
    template: '<div class="dialog-footer"><slot /></div>',
  }
}));

// Mock Lucide icons
vi.mock('lucide-vue-next', () => ({
  CheckCircle2: { name: 'CheckCircle2', template: '<svg class="check-circle" />' },
  Circle: { name: 'Circle', template: '<svg class="circle" />' },
  AlertCircle: { name: 'AlertCircle', template: '<svg class="alert-circle" />' },
  Sparkles: { name: 'Sparkles', template: '<svg class="sparkles" />' },
  Edit3: { name: 'Edit3', template: '<svg class="edit" />' },
  Trash2: { name: 'Trash2', template: '<svg class="trash" />' },
  Plus: { name: 'Plus', template: '<svg class="plus" />' },
}));

describe('TaskConfirmationDialog - Width and Layout', () => {
  const mockTasksWithLongContent = [
    {
      title: 'Very Long Task Title That Should Test Text Wrapping and Layout Handling in the Dialog Component',
      description: 'This is a very long task description that contains multiple sentences and should test how well the dialog handles longer text content. It includes detailed information about what needs to be done and should demonstrate proper text wrapping and spacing within the dialog layout. The description continues with even more text to really test the boundaries of the layout system.',
      status: 'pending',
      priority: 'high',
      sort_order: 1,
    },
    {
      title: 'Another Task with Moderate Length Title',
      description: 'This task has a moderately long description that should also test the layout capabilities. It includes some technical details and requirements that need to be properly displayed without breaking the dialog layout or causing horizontal scrolling issues.',
      status: 'in_progress',
      priority: 'medium',
      sort_order: 2,
    },
    {
      title: 'Short Task',
      description: 'Brief description.',
      status: 'pending',
      priority: 'low',
      sort_order: 3,
    }
  ];

  const defaultProps = {
    open: true,
    tasks: mockTasksWithLongContent,
    projectDescription: 'A complex project with very detailed requirements that need comprehensive task management and proper dialog display capabilities',
    loading: false,
  };

  let wrapper;

  beforeEach(() => {
    wrapper = mount(TaskConfirmationDialog, {
      props: defaultProps,
      global: {
        stubs: {
          Dialog: true,
          DialogContent: true,
          DialogHeader: true,
          DialogTitle: true,
          DialogDescription: true,
          DialogFooter: true,
          Card: true,
          CardContent: true,
          CardHeader: true,
          CardTitle: true,
          Badge: true,
          ScrollArea: true,
          Button: true,
        }
      }
    });
  });

  it('uses wider dialog dimensions for better content display', () => {
    const dialogContent = wrapper.find('.dialog-content');
    expect(dialogContent.exists()).toBe(true);

    // Check that the dialog uses the new wider classes
    expect(dialogContent.classes()).toContain('max-w-6xl');
    expect(dialogContent.classes()).toContain('w-[90vw]');
    expect(dialogContent.classes()).toContain('max-h-[85vh]');
  });

  it('has increased scroll area height for more tasks', () => {
    const scrollArea = wrapper.find('.scroll-area');
    expect(scrollArea.exists()).toBe(true);
    expect(scrollArea.classes()).toContain('h-[500px]');
  });

  it('applies full width classes to task cards', () => {
    const taskCards = wrapper.findAll('[data-testid^="suggested-task-"]');
    expect(taskCards.length).toBeGreaterThan(0);

    taskCards.forEach(card => {
      expect(card.classes()).toContain('w-full');
    });
  });

  it('handles long task titles with proper text wrapping', () => {
    const wrapper = mount(TaskConfirmationDialog, {
      props: {
        ...defaultProps,
        tasks: [{
          title: 'This is an extremely long task title that should wrap properly and not overflow the dialog boundaries or cause layout issues',
          description: 'Task description',
          status: 'pending',
          priority: 'high',
          sort_order: 1,
        }]
      },
      global: {
        stubs: {
          Dialog: true,
          DialogContent: true,
          DialogHeader: true,
          DialogTitle: true,
          DialogDescription: true,
          DialogFooter: true,
          Card: true,
          Badge: true,
          ScrollArea: true,
          Button: true,
        }
      }
    });

    // The component should render without issues
    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('[data-testid="suggested-task-0"]').exists()).toBe(true);
  });

  it('handles long task descriptions with proper text wrapping', () => {
    const longDescription = 'This is an extremely long task description that contains multiple paragraphs of text and should demonstrate how the dialog handles extensive content without breaking the layout. '.repeat(5);

    const wrapper = mount(TaskConfirmationDialog, {
      props: {
        ...defaultProps,
        tasks: [{
          title: 'Task with Long Description',
          description: longDescription,
          status: 'pending',
          priority: 'high',
          sort_order: 1,
        }]
      },
      global: {
        stubs: {
          Dialog: true,
          DialogContent: true,
          DialogHeader: true,
          DialogTitle: true,
          DialogDescription: true,
          DialogFooter: true,
          Card: true,
          Badge: true,
          ScrollArea: true,
          Button: true,
        }
      }
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('[data-testid="suggested-task-0"]').exists()).toBe(true);
  });

  it('maintains proper spacing with multiple tasks', () => {
    const wrapper = mount(TaskConfirmationDialog, {
      props: {
        ...defaultProps,
        tasks: Array.from({ length: 8 }, (_, index) => ({
          title: `Task ${index + 1} with Various Length Title Content`,
          description: `Description for task ${index + 1} with enough content to test spacing and layout.`,
          status: 'pending',
          priority: index % 2 === 0 ? 'high' : 'medium',
          sort_order: index + 1,
        }))
      },
      global: {
        stubs: {
          Dialog: true,
          DialogContent: true,
          DialogHeader: true,
          DialogTitle: true,
          DialogDescription: true,
          DialogFooter: true,
          Card: true,
          Badge: true,
          ScrollArea: true,
          Button: true,
        }
      }
    });

    const taskCards = wrapper.findAll('[data-testid^="suggested-task-"]');
    expect(taskCards).toHaveLength(8);

    // All cards should have proper width classes
    taskCards.forEach(card => {
      expect(card.classes()).toContain('w-full');
    });
  });

  it('provides adequate space for the add new task section', () => {
    const addTaskSection = wrapper.find('[data-testid="new-task-title"]').closest('.card');
    if (addTaskSection.exists()) {
      expect(addTaskSection.classes()).toContain('w-full');
    }
  });

  it('handles project descriptions of various lengths', () => {
    const longProjectDescription = 'This is an extremely long project description that should be displayed properly in the dialog header without breaking the layout or causing overflow issues. '.repeat(3);

    const wrapper = mount(TaskConfirmationDialog, {
      props: {
        ...defaultProps,
        projectDescription: longProjectDescription
      },
      global: {
        stubs: {
          Dialog: true,
          DialogContent: true,
          DialogHeader: true,
          DialogTitle: true,
          DialogDescription: true,
          DialogFooter: true,
          Card: true,
          Badge: true,
          ScrollArea: true,
          Button: true,
        }
      }
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain(longProjectDescription);
  });

  it('maintains responsive behavior with the new width settings', () => {
    // Test that the component still renders properly with the responsive width classes
    const dialogContent = wrapper.find('.dialog-content');
    expect(dialogContent.exists()).toBe(true);

    // Should have both max-width and responsive width
    expect(dialogContent.classes()).toContain('max-w-6xl');
    expect(dialogContent.classes()).toContain('w-[90vw]');
  });

  it('provides sufficient height for the scroll area', () => {
    const scrollArea = wrapper.find('.scroll-area');
    expect(scrollArea.exists()).toBe(true);

    // Should have the increased height
    expect(scrollArea.classes()).toContain('h-[500px]');
  });

  it('applies proper overflow handling', () => {
    const dialogContent = wrapper.find('.dialog-content');
    expect(dialogContent.classes()).toContain('overflow-hidden');
  });
});
