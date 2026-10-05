// End-to-end journey J-MN-2/J-MN-3 against a real openDesk Jitsi:
// two signed-in people join a meeting with speech audio; the first starts
// meeting notes from the floating button, the second sees the banner; after
// the talk the first stops, and the "saved" notice links to the file.
//
//   SYN_MEET=https://meet.example.org SYN_ACCOUNT=https://id.example.org/realms/opendesk/account/ \
//   SYN_USER1=demo1 SYN_PASS1=... SYN_USER2=demo2 SYN_PASS2=... \
//   SYN_AUDIO1=voice-a.wav SYN_AUDIO2=voice-b.wav [SYN_SECONDS=70] [SYN_OUT=/tmp/synascriber-e2e] \
//   PLAYWRIGHT=/path/to/node_modules/playwright/index.mjs node tests/e2e/meeting-notes.mjs
//
// Audio: 48 kHz, 16-bit, mono WAV (Chrome's fake microphone loops it).
const need = (name) => {
  const value = process.env[name]
  if (!value) {
    throw new Error(`${name} is not set`)
  }
  return value
}

const { chromium } = await import(process.env.PLAYWRIGHT || 'playwright')
const MEET = need('SYN_MEET').replace(/\/$/, '')
const ACCOUNT = need('SYN_ACCOUNT')
const SECONDS = Number(process.env.SYN_SECONDS || 70)
const OUT = process.env.SYN_OUT || '/tmp/synascriber-e2e'
const ROOM = process.env.SYN_ROOM || `synascriber-e2e-${Date.now().toString(36)}`
// The loader speaks Jitsi's UI language; pick the labels to match (SYN_UI=en|de).
const LABELS = {
  en: {
    button: 'Meeting notes', language: 'Meeting language', start: 'Start meeting notes', running: /Notes on/, banner: /Notes on · started by/,
    stop: 'Stop notes', saved: /Notes saved in Synaplan Files/, open: 'Open the notes',
  },
  de: {
    button: 'Mitschrift', language: 'Sprache der Besprechung', start: 'Mitschrift starten', running: /Mitschrift läuft/, banner: /Mitschrift läuft · gestartet von/,
    stop: 'Mitschrift beenden', saved: /Mitschrift gespeichert in Synaplan Dateien/, open: 'Mitschrift öffnen',
  },
}
const L = LABELS[process.env.SYN_UI || 'en']
const t0 = Date.now()
const log = (...args) => console.log(`[${((Date.now() - t0) / 1000).toFixed(1).padStart(6)}s]`, ...args)

async function participant(label, user, password, audio) {
  const browser = await chromium.launch({
    headless: true,
    args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', `--use-file-for-fake-audio-capture=${audio}`, '--autoplay-policy=no-user-gesture-required'],
  })
  const context = await browser.newContext({ permissions: ['microphone', 'camera'], locale: 'de-DE', viewport: { width: 1280, height: 800 } })
  const page = await context.newPage()
  page.on('pageerror', (error) => log(label, 'page error:', error.message))
  page.on('console', (message) => {
    if (message.type() === 'error' && /synascriber|plugins\/synascriber/i.test(message.text())) {
      log(label, 'console:', message.text().slice(0, 200))
    }
  })

  await page.goto(ACCOUNT)
  await page.fill('#username', user)
  await page.fill('#password', password)
  await page.click('#kc-login')
  await page.waitForURL(/\/account/, { timeout: 30000 })

  await page.goto(`${MEET}/${ROOM}#config.startWithVideoMuted=true`)
  const deadline = Date.now() + 90000
  for (;;) {
    if (Date.now() > deadline) {
      await page.screenshot({ path: `${OUT}/${label}-join-failed.png` })
      throw new Error(`${label} did not join (at ${page.url()})`)
    }
    if (page.url().includes('/realms/') && (await page.locator('#username').count()) > 0) {
      await page.fill('#username', user)
      await page.fill('#password', password)
      await page.click('#kc-login')
    }
    if (await page.evaluate(() => Boolean(window.APP?.conference?.isJoined?.())).catch(() => false)) {
      break
    }
    const join = page.locator('[data-testid="prejoin.joinMeeting"]')
    if ((await join.count()) > 0 && (await join.isVisible().catch(() => false))) {
      await join.click().catch(() => {})
    }
    await page.waitForTimeout(1000)
  }
  log(label, `joined ${ROOM} as ${user}`)
  return { browser, page, label }
}

const notes = (page) => page.locator('#synascriber')
async function waitForText(page, pattern, timeoutMs, what) {
  await notes(page).getByText(pattern).first().waitFor({ timeout: timeoutMs }).catch(async (error) => {
    await page.screenshot({ path: `${OUT}/failed-${what}.png` })
    throw new Error(`${what}: ${error.message.split('\n')[0]}`)
  })
}

const a = await participant('A', need('SYN_USER1'), need('SYN_PASS1'), need('SYN_AUDIO1'))
await notes(a.page).getByRole('button', { name: L.button, exact: true }).waitFor({ timeout: 45000 }).catch(async (error) => {
  await a.page.screenshot({ path: `${OUT}/failed-button-visible.png` })
  throw error
})
log('A', 'floating button visible')
await a.page.screenshot({ path: `${OUT}/1-button.png` })

const b = await participant('B', need('SYN_USER2'), need('SYN_PASS2'), need('SYN_AUDIO2'))

await notes(a.page).getByRole('button', { name: L.button, exact: true }).click()
await notes(a.page).getByRole('dialog').waitFor()
await notes(a.page).getByLabel(L.language).selectOption('de')
await a.page.screenshot({ path: `${OUT}/2-dialog.png` })
await notes(a.page).getByRole('button', { name: L.start }).click()
log('A', 'start clicked (meeting language: Deutsch)')

await waitForText(a.page, L.running, 45000, 'running-for-starter')
log('A', 'notes running')
await a.page.screenshot({ path: `${OUT}/3-running-starter.png` })
await waitForText(b.page, L.banner, 45000, 'banner-for-participant')
log('B', 'sees the banner')
await b.page.screenshot({ path: `${OUT}/4-banner-participant.png` })

const jitsiSays = await b.page.evaluate(() => APP.store.getState()['features/transcribing']?.isTranscribing ?? null)
log('B', `Jitsi's own indicator: isTranscribing=${jitsiSays}`)

log('both', `talking for ${SECONDS}s`)
await a.page.waitForTimeout(SECONDS * 1000)

await notes(a.page).getByRole('button', { name: L.stop }).click()
log('A', 'stop clicked')
await waitForText(a.page, L.saved, 180000, 'saved-notice')
const link = await notes(a.page).getByRole('link', { name: L.open }).getAttribute('href')
log('A', `saved; link ${link}`)
await a.page.screenshot({ path: `${OUT}/5-saved.png` })

await Promise.all([a.browser.close(), b.browser.close()])
console.log(JSON.stringify({ ok: true, room: ROOM, link, seconds: Math.round((Date.now() - t0) / 1000) }))
