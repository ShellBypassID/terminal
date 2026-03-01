# 🚀 Ribel Web Terminal - PHP Based Remote Terminal

![Version](https://img.shields.io/badge/version-2.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.4+-green)
![License](https://img.shields.io/badge/license-MIT-orange)
![Bootstrap](https://img.shields.io/badge/Bootstrap-Icons-purple)

<p align="center">
  <img src="https://raw.githubusercontent.com/ShellBypassID/terminal/refs/heads/main/images/TerminalMain.png" alt="Web Terminal Screenshot" width="800">
  <br>
  <em>Web-based terminal dengan berbagai metode eksekusi dan fitur lengkap</em>
</p>

## 📋 **Deskripsi**

Web Terminal adalah sebuah aplikasi PHP yang menyediakan akses terminal melalui browser. Dilengkapi dengan berbagai metode eksekusi perintah dan fitur-fitur canggih untuk memudahkan administrasi server.

### 🗝️ **Password Default**
```bash
admin123
```

### ✨ **Fitur Utama**

- 🔐 **Login System** - Password protected access
- 📁 **Directory Navigation** - `cd`, `pwd`, `ls` dengan history
- 💾 **Save/Load Directories** - Simpan lokasi favorit
- 🔙 **Quick Back** - Kembali ke direktori script dengan `back`
- 📜 **Command History** - Navigasi dengan arrow up/down
- 🎯 **Multi-Method Execution** - 10+ metode eksekusi
- 🐧 **Cross Platform** - Linux & Windows support
- 🎨 **Bootstrap Icons** - Tampilan modern dan responsif
- 🔧 **Debug Mode** - Lihat metode eksekusi yang digunakan

## 🛠️ **Metode Eksekusi**

Web Terminal menggunakan berbagai metode untuk menjalankan perintah:

| Metode | Ketersediaan | Keterangan |
|--------|--------------|------------|
| `shell_exec` | ⭐ Paling umum | Eksekusi langsung |
| `exec` | ✅ Alternatif | Dengan return array |
| `system` | ✅ Alternatif | Output buffering |
| `passthru` | ✅ Alternatif | Untuk binary output |
| `popen` | ✅ Alternatif | Baca via pipe |
| `proc_open` | ✅ Paling lengkap | Kontrol penuh |
| `` `backtick` `` | ✅ Alternatif | Operator PHP |
| `PHP Native` | ✅ Selalu ada | Fallback terakhir |
