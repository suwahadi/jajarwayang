/**
 * Komponen Alpine "wysiwyg" — editor WYSIWYG ringan tanpa dependensi.
 *
 * Memakai `contenteditable` + perintah format bawaan browser
 * (document.execCommand). Output berupa HTML yang langsung ter-bind ke
 * properti Livewire — tidak ada state ProseMirror/transaksi yang bisa
 * desinkron, sehingga aman di dalam modal Flux + wire:ignore.
 *
 * Pemakaian (lihat resources/views/components/wysiwyg.blade.php):
 *   x-data="wysiwyg('content', 'Tulis di sini…')"
 *   <div x-ref="editor" contenteditable></div>
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('wysiwyg', (model, placeholder = '') => ({
        tick: 0, // pemicu reaktif agar status tombol toolbar ikut ter-update

        init() {
            const el = this.$refs.editor

            // Cegah inisialisasi ganda (Alpine bisa re-init di dalam modal).
            if (el.dataset.ready) return
            el.dataset.ready = '1'

            // Gunakan tag (<b>, <i>, …) alih-alih inline style saat memformat.
            try {
                document.execCommand('styleWithCSS', false, false)
            } catch (e) {
                /* sebagian browser melempar; abaikan */
            }

            // Isi awal dari Livewire (mis. saat membuka modal edit).
            el.innerHTML = this.$wire.get(model) || ''
            this.refreshEmpty()

            const push = () => {
                this.$wire.set(model, el.innerHTML, false) // defer; ikut terkirim saat submit
                this.refreshEmpty()
            }
            const refresh = () => this.tick++

            el.addEventListener('input', () => {
                push()
                refresh()
            })
            el.addEventListener('keyup', refresh)
            el.addEventListener('mouseup', refresh)
            document.addEventListener('selectionchange', () => {
                if (document.activeElement === el) this.tick++
            })

            // Refleksikan perubahan dari server (reset setelah simpan, memuat
            // data saat edit). Hanya tulis ulang bila editor tidak sedang fokus
            // agar tidak mengganggu ketikan pengguna.
            this.$wire.$watch(model, (value) => {
                const incoming = value || ''
                if (incoming !== el.innerHTML && document.activeElement !== el) {
                    el.innerHTML = incoming
                    this.refreshEmpty()
                }
            })
        },

        // Tampilkan placeholder saat konten kosong.
        refreshEmpty() {
            const el = this.$refs.editor
            const empty = el.textContent.trim() === '' && !el.querySelector('img')
            el.classList.toggle('is-empty', empty)
        },

        // --- Aksi toolbar ---
        // Dipanggil via @mousedown.prevent agar seleksi di editor tidak hilang.
        cmd(command, value = null) {
            this.$refs.editor.focus()
            document.execCommand(command, false, value)
            this.$wire.set(model, this.$refs.editor.innerHTML, false)
            this.refreshEmpty()
            this.tick++
        },

        // Toggle blok (heading/quote/pre): klik lagi untuk kembali ke paragraf.
        block(tag) {
            const current = (document.queryCommandValue('formatBlock') || '').toLowerCase()
            this.cmd('formatBlock', current === tag ? '<p>' : '<' + tag + '>')
        },

        // --- Status tombol ---
        state(command) {
            this.tick // sentuh agar reaktif terhadap perubahan seleksi
            try {
                return document.queryCommandState(command)
            } catch (e) {
                return false
            }
        },

        isBlock(tag) {
            this.tick
            try {
                return (document.queryCommandValue('formatBlock') || '').toLowerCase() === tag
            } catch (e) {
                return false
            }
        },

        setLink() {
            const url = window.prompt('Masukkan URL tautan:', 'https://')
            if (url) this.cmd('createLink', url)
        },

        setImage() {
            const url = window.prompt('Masukkan URL gambar:', 'https://')
            if (url) this.cmd('insertImage', url)
        },
    }))
})
