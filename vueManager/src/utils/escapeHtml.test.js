import { describe, expect, it } from 'vitest'

import { escapeHtml, renderTemplate } from './escapeHtml.js'

const productLink =
  '<span class="product-id">({id})</span> <a href="?a=resource/update&id={id}" target="_blank" class="product-link">{pagetitle}</a>'

describe('escapeHtml', () => {
  it('escapes markup characters', () => {
    expect(escapeHtml(`a<b>&"'`)).toBe('a&lt;b&gt;&amp;&quot;&#39;')
    expect(escapeHtml(null)).toBe('')
  })
})

describe('renderTemplate', () => {
  it('keeps the link markup and escapes the title', () => {
    const html = renderTemplate(productLink, {
      id: 15,
      pagetitle: `Coat <b class="x">`,
    })

    expect(html).toContain('href="?a=resource/update&id=15"')
    expect(html).toContain('(15)')
    expect(html).toContain('Coat &lt;b class=&quot;x&quot;&gt;')
    expect(html).not.toContain('<b')
  })

  it('replaces a missing field with an empty string', () => {
    expect(renderTemplate('{id}-{pagetitle}', { id: 3 })).toBe('3-')
  })
})
