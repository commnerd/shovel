/**
 * @jest-environment jsdom
 */

import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import Badge from '@/components/ui/badge/Badge.vue';

// Mock the cn utility function
vi.mock('@/lib/utils', () => ({
  cn: (...classes) => classes.filter(Boolean).join(' ')
}));

describe('Badge Component', () => {
  it('renders with default variant', () => {
    const wrapper = mount(Badge, {
      slots: {
        default: 'Test Badge'
      }
    });

    expect(wrapper.text()).toBe('Test Badge');
    expect(wrapper.find('div').exists()).toBe(true);
  });

  it('applies default variant classes', () => {
    const wrapper = mount(Badge, {
      slots: {
        default: 'Default Badge'
      }
    });

    const badgeElement = wrapper.find('div');
    expect(badgeElement.classes()).toContain('inline-flex');
    expect(badgeElement.classes()).toContain('items-center');
    expect(badgeElement.classes()).toContain('rounded-full');
    expect(badgeElement.classes()).toContain('border');
    expect(badgeElement.classes()).toContain('px-2.5');
    expect(badgeElement.classes()).toContain('py-0.5');
    expect(badgeElement.classes()).toContain('text-xs');
    expect(badgeElement.classes()).toContain('font-semibold');
  });

  it('applies secondary variant classes', () => {
    const wrapper = mount(Badge, {
      props: {
        variant: 'secondary'
      },
      slots: {
        default: 'Secondary Badge'
      }
    });

    const badgeElement = wrapper.find('div');
    expect(badgeElement.classes()).toContain('border-transparent');
    expect(badgeElement.classes()).toContain('bg-secondary');
    expect(badgeElement.classes()).toContain('text-secondary-foreground');
    expect(badgeElement.classes()).toContain('hover:bg-secondary/80');
  });

  it('applies destructive variant classes', () => {
    const wrapper = mount(Badge, {
      props: {
        variant: 'destructive'
      },
      slots: {
        default: 'Destructive Badge'
      }
    });

    const badgeElement = wrapper.find('div');
    expect(badgeElement.classes()).toContain('border-transparent');
    expect(badgeElement.classes()).toContain('bg-destructive');
    expect(badgeElement.classes()).toContain('text-destructive-foreground');
    expect(badgeElement.classes()).toContain('hover:bg-destructive/80');
  });

  it('applies outline variant classes', () => {
    const wrapper = mount(Badge, {
      props: {
        variant: 'outline'
      },
      slots: {
        default: 'Outline Badge'
      }
    });

    const badgeElement = wrapper.find('div');
    expect(badgeElement.classes()).toContain('text-foreground');
  });

  it('accepts custom class prop', () => {
    const wrapper = mount(Badge, {
      props: {
        class: 'custom-class'
      },
      slots: {
        default: 'Custom Badge'
      }
    });

    const badgeElement = wrapper.find('div');
    expect(badgeElement.classes()).toContain('custom-class');
  });

  it('merges custom classes with variant classes', () => {
    const wrapper = mount(Badge, {
      props: {
        variant: 'secondary',
        class: 'custom-spacing'
      },
      slots: {
        default: 'Merged Badge'
      }
    });

    const badgeElement = wrapper.find('div');
    expect(badgeElement.classes()).toContain('bg-secondary');
    expect(badgeElement.classes()).toContain('custom-spacing');
  });

  it('renders slot content correctly', () => {
    const wrapper = mount(Badge, {
      slots: {
        default: '<span>HTML Content</span>'
      }
    });

    expect(wrapper.html()).toContain('<span>HTML Content</span>');
  });

  it('handles empty slot content', () => {
    const wrapper = mount(Badge);

    expect(wrapper.find('div').exists()).toBe(true);
    expect(wrapper.text()).toBe('');
  });

  it('applies focus and transition classes', () => {
    const wrapper = mount(Badge, {
      slots: {
        default: 'Focus Badge'
      }
    });

    const badgeElement = wrapper.find('div');
    expect(badgeElement.classes()).toContain('transition-colors');
    expect(badgeElement.classes()).toContain('focus:outline-none');
    expect(badgeElement.classes()).toContain('focus:ring-2');
    expect(badgeElement.classes()).toContain('focus:ring-ring');
    expect(badgeElement.classes()).toContain('focus:ring-offset-2');
  });

  it('maintains component structure after TypeScript fixes', () => {
    // Test that the component still works after fixing the HTMLAttributes extension issue
    const wrapper = mount(Badge, {
      props: {
        variant: 'default'
      },
      slots: {
        default: 'Fixed Badge'
      }
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.find('div').exists()).toBe(true);
    expect(wrapper.text()).toBe('Fixed Badge');

    // Ensure the component accepts the variant prop correctly
    expect(wrapper.props('variant')).toBe('default');
  });

  it('works with all supported variant types', () => {
    const variants = ['default', 'secondary', 'destructive', 'outline'];

    variants.forEach(variant => {
      const wrapper = mount(Badge, {
        props: { variant },
        slots: { default: `${variant} badge` }
      });

      expect(wrapper.exists()).toBe(true);
      expect(wrapper.text()).toBe(`${variant} badge`);
    });
  });

  it('properly handles class attribute inheritance', () => {
    const wrapper = mount(Badge, {
      attrs: {
        'data-testid': 'test-badge',
        'aria-label': 'Test badge'
      },
      slots: {
        default: 'Attributed Badge'
      }
    });

    const badgeElement = wrapper.find('div');
    expect(badgeElement.attributes('data-testid')).toBe('test-badge');
    expect(badgeElement.attributes('aria-label')).toBe('Test badge');
  });
});
