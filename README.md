<div align="center">

# 🎵 Apollo Lyre: WEB-BASED MUSIC LIBRARY, PLAYLIST AND TRACK MANAGEMENT SYSTEM

**Organise your music. Understand your listening. All in one place.**

![Course](https://img.shields.io/badge/Course-CSC264-6C3FC5?style=for-the-badge)
![Type](https://img.shields.io/badge/Type-Web%20App-1DB954?style=for-the-badge)
![Spotify](https://img.shields.io/badge/Integrates-Spotify%20Web%20API-1DB954?style=for-the-badge&logo=spotify&logoColor=white)
![Status](https://img.shields.io/badge/Status-In%20Development-F5A623?style=for-the-badge)

<br>

<a href="https://nzjealousy.github.io/ApolloLyre/">
  <img src="https://img.shields.io/badge/🌐%20Visit%20the%20Website-GitHub%20Pages-1DB954?style=for-the-badge&logo=github&logoColor=white" alt="Try out Apollo Lyre" height="48">
</a>

</div>

---

## 📖 About

**Apollo Lyre: "NEAT" Music Manager** is a web-based tool that connects to your **Spotify** account so you can manage your songs, build playlists, and view your listening analytics, all in one clean and simple place.

The name comes from **Apollo**, the Greek god of music, and the **lyre**, the instrument associated with him.

This project was created for **CSC264 – Introduction to Web and Mobile Application** (Diploma in Computer Science, CDCS110) at Universiti Teknologi MARA (UiTM), Semester March 2026 – August 2026.

---

## 🤔 The Problem

Streaming gives us millions of songs, but the tools to understand and manage our listening are limited:

| Problem | What it means |
| --- | --- |
| 📉 **Limited listening stats** | Spotify shows recent plays, top songs and a yearly Wrapped, but no full listening history. |
| ⏳ **Playlists take effort** | Songs are searched for and added one by one, then renamed and reordered by hand. |
| 🗂️ **Saved songs are disorganised** | One long list with no ratings, mood tags, or notes. |
| 🧩 **Tools are scattered** | Stats live in services like Last.fm or stats.fm, while songs live in streaming apps. |

**NEAT brings it all together in one platform.**

---

## ✨ Features

### 🎧 Music Management
- Search songs and save them to your library
- Build and manage playlists (add, rename, reorder, tidy) with less manual effort
- **Rate** your songs
- Add **mood or activity tags** (e.g. study, workout, chill)
- Leave **personal comments** about why you saved a song

### 📊 Listening Analytics
- Full listening history and totals (hours listened, songs played)
- Most and least played songs and artists
- Top artists, top tracks and genre breakdowns on a personal dashboard
- See how your taste changes over time, since your data is saved

### 🛠️ Admin Dashboard
- Overall stats such as total users, common genres and most listened artists
- Shown as **aggregates only**, never individual listening records
- Account management

### 🔒 Privacy and Ethics
- Spotify is connected **only with your consent**
- Passwords are stored in **encrypted** form
- You control your profile; deleting your account removes your stored data
- Follows [Spotify's developer terms](https://developer.spotify.com/terms) and only uses data the API allows

---

## 🧱 System Modules

| Module | What it does |
| --- | --- |
| 👤 **User Management** | Register, login/logout, profile, admin control of accounts |
| 🎶 **Music and Playlist Management** | Search, save, rate, tag, comment, create and edit playlists |
| 📈 **Listening Analytics** | History, totals, top and least played songs and artists, genres |

Each module supports **insert, update, remove, search and view** operations.

---

## 🛠️ Tech Stack

> ✏️ *Edit this section to match what your team actually used.*

| Layer | Technology |
| --- | --- |
| Frontend | HTML, CSS, JavaScript |
| Backend | *(e.g. PHP / Node.js)* |
| Database | *(e.g. MySQL)* |
| API | Spotify Web API |

---

## 🚀 Getting Started

> ✏️ *Adjust the steps below to match your project setup.*

### Prerequisites
- A web server environment (e.g. XAMPP or Node.js)
- A database server
- A [Spotify Developer](https://developer.spotify.com/) account with an app created

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/<your-username>/apollo-lyre.git

# 2. Go into the project folder
cd apollo-lyre
```

### Configuration

1. Create an app in the [Spotify Developer Dashboard](https://developer.spotify.com/dashboard).
2. Copy your **Client ID** and **Client Secret**.
3. Add your **Redirect URI** in the Spotify app settings.
4. Put your keys in your config file (never commit real secrets):

```env
SPOTIFY_CLIENT_ID=your_client_id_here
SPOTIFY_CLIENT_SECRET=your_client_secret_here
SPOTIFY_REDIRECT_URI=http://localhost/callback
```

5. Import the database file and start your server.

---

## 📁 Project Structure

> ✏️ *Update to match your real folders.*

```
apollo-lyre/
├── assets/          # images, icons, styles
├── includes/        # shared PHP/JS files
├── database/        # SQL file for the database
├── pages/           # user and admin pages
├── index.html
└── README.md
```

---

## 👥 Team

| Name | Role |
| --- | --- |
| **Nicholas Langit** | Project Manager |
| **Taufiqurrijal Bin Adey** | Lead Developer (Spotify Web API integration) |
| **Aina Maisara Binti Nazaruddin** | Database Designer |
| **Izzati Najihah Bt Arman** | Interface Designer (UX) |

**Supervisor / Lecturer:** Madam Ketty Anak Chachil

---

## 🗓️ Project Timeline

| Phase | Status |
| --- | --- |
| Project proposal | ✅ Done |
| Web app storyboard | 🔄 In progress |
| Development | ⏳ Upcoming |
| Testing and finalising | ⏳ Upcoming |
| Final report | ⏳ Upcoming |

---

## 📚 References

- [Spotify Developer Terms](https://developer.spotify.com/terms)
- [Spotify Web API Changelog: February 2026](https://developer.spotify.com/documentation/web-api/references/changes/february-2026)
- [Update on Developer Access and Platform Security](https://developer.spotify.com/blog/2026-02-06-update-on-developer-access-and-platform-security)
- [Last.fm](https://www.last.fm/home)

---

## 📄 License and Disclaimer

This is a student project for academic purposes. It is not affiliated with or endorsed by Spotify.

<div align="center">

Made with 🎶 by the Apollo Lyre team

</div>
