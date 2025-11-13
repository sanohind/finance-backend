# Frontend API Guide - News & Carousel Images

Dokumentasi API untuk fitur News dengan Carousel Images untuk role Finance.

## Base URL

```
/api/finance
```

## Authentication

Semua endpoint memerlukan authentication dengan Sanctum token.

-   Header: `Authorization: Bearer {token}`
-   Role required: `2` (Finance)

---

## 📁 Lokasi Penyimpanan File

### Carousel Images

**Lokasi Fisik:**

```
storage/app/public/news_carousel/
```

**Contoh Path File:**

```
storage/app/public/news_carousel/CAROUSEL_news_title_1.jpg
storage/app/public/news_carousel/CAROUSEL_news_title_2.png
```

**URL Akses (via Web):**

```
http://your-domain.com/storage/news_carousel/CAROUSEL_news_title_1.jpg
```

**Format Nama File:**

-   Pattern: `CAROUSEL_{slug_title}_{index}.{extension}`
-   Contoh: `CAROUSEL_news_title_1.jpg`, `CAROUSEL_news_title_2.png`
-   Jika title tidak ada, menggunakan timestamp: `CAROUSEL_CAROUSEL_20241112030000_1.jpg`

### Documents

**Lokasi Fisik:**

```
storage/app/public/news_documents/
```

**URL Akses:**

```
http://your-domain.com/storage/news_documents/NEWS_news_title.pdf
```

### ⚠️ Penting: Setup Storage Link

Untuk mengakses file via URL, pastikan symlink sudah dibuat:

```bash
php artisan storage:link
```

Command ini akan membuat symlink dari `public/storage` ke `storage/app/public`, sehingga file bisa diakses via browser.

**Struktur Folder:**

```
project-root/
├── storage/
│   └── app/
│       └── public/
│           ├── news_carousel/      ← Carousel images disimpan di sini
│           │   ├── CAROUSEL_news_title_1.jpg
│           │   └── CAROUSEL_news_title_2.png
│           └── news_documents/     ← Documents disimpan di sini
│               └── NEWS_news_title.pdf
└── public/
    └── storage -> ../storage/app/public  ← Symlink (dibuat oleh storage:link)
```

---

## 📰 News Endpoints

### 1. Get All News

**GET** `/api/finance/news`

**Response:**

```json
{
    "data": [
        {
            "id": 1,
            "title": "News Title",
            "carousel_images": [
                {
                    "path": "news_carousel/CAROUSEL_news_title_1.jpg",
                    "url": "http://domain/storage/news_carousel/CAROUSEL_news_title_1.jpg",
                    "filename": "CAROUSEL_news_title_1.jpg"
                }
            ],
            "start_date": "2024-11-01",
            "end_date": "2024-11-30",
            "document": "news_documents/NEWS_news_title.pdf",
            "created_by": "John Doe",
            "updated_by": null,
            "created_at": "2024-11-12T10:00:00.000000Z",
            "updated_at": "2024-11-12T10:00:00.000000Z"
        }
    ]
}
```

---

### 2. Create News

**POST** `/api/finance/news/store`

**Request (Form Data):**

-   `title` (string, optional) - Max 255 characters
-   `start_date` (date, optional) - Format: YYYY-MM-DD
-   `end_date` (date, optional) - Must be >= start_date if start_date provided
-   `document` (file, optional) - Allowed: pdf, doc, docx, jpg, jpeg, png (max 5000 KB)
-   `carousel_images[]` (array of files, optional) - Allowed: jpeg, jpg, png, webp (max 2048 KB each)

**Note:** Minimal harus ada salah satu: `title`, `document`, atau `carousel_images`

**Example (JavaScript - FormData):**

```javascript
const formData = new FormData();
formData.append("title", "News Title");
formData.append("start_date", "2024-11-01");
formData.append("end_date", "2024-11-30");
formData.append("document", documentFile);

// Upload multiple carousel images
carouselFiles.forEach((file, index) => {
    formData.append("carousel_images[]", file);
});

fetch("/api/finance/news/store", {
    method: "POST",
    headers: {
        Authorization: `Bearer ${token}`,
    },
    body: formData,
});
```

**Example (Upload hanya carousel images):**

```javascript
const formData = new FormData();
carouselFiles.forEach((file) => {
    formData.append("carousel_images[]", file);
});

fetch("/api/finance/news/store", {
    method: "POST",
    headers: {
        Authorization: `Bearer ${token}`,
    },
    body: formData,
});
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "title": "News Title",
    "carousel_images": [...],
    "start_date": "2024-11-01",
    "end_date": "2024-11-30",
    "document": "news_documents/NEWS_news_title.pdf",
    "created_by": "John Doe",
    "updated_by": null,
    "created_at": "2024-11-12T10:00:00.000000Z",
    "updated_at": "2024-11-12T10:00:00.000000Z"
  }
}
```

---

### 3. Get Single News

**GET** `/api/finance/news/edit/{id}`

**Response:** Same format as Get All News (single object)

---

### 4. Update News

**PUT** `/api/finance/news/update/{id}`

**Request (Form Data):**

-   `title` (string, optional)
-   `start_date` (date, optional)
-   `end_date` (date, optional)
-   `document` (file, optional)
-   `carousel_images[]` (array of files, optional) - Will be appended to existing images

**Example:**

```javascript
const formData = new FormData();
formData.append("title", "Updated Title");
formData.append("_method", "PUT"); // Laravel method spoofing

fetch(`/api/finance/news/update/${newsId}`, {
    method: "POST",
    headers: {
        Authorization: `Bearer ${token}`,
    },
    body: formData,
});
```

**Response:** Same format as Create News

---

### 5. Delete News

**DELETE** `/api/finance/news/delete/{id}`

**Response:**

```json
{
    "message": "News deleted successfully."
}
```

**Note:** This will also delete all associated carousel images and document files.

---

### 6. Stream Document

**GET** `/api/finance/news/document/{filename}`

**Example:**

```html
<img src="/api/finance/news/document/NEWS_news_title.pdf" />
```

---

## 🖼️ Carousel Images Endpoints

### 1. Upload Carousel Image

**POST** `/api/finance/news/{id}/carousel/upload`

**Request (Form Data):**

-   `image` (file, required) - Allowed: jpeg, jpg, png, webp (max 2048 KB)

**Example:**

```javascript
const formData = new FormData();
formData.append("image", imageFile);

fetch(`/api/finance/news/${newsId}/carousel/upload`, {
    method: "POST",
    headers: {
        Authorization: `Bearer ${token}`,
    },
    body: formData,
});
```

**Response:** Same format as Get All News (updated news object)

---

### 2. Update Carousel Image

**PUT** `/api/finance/news/{id}/carousel/{imageIndex}`

**Request (Form Data):**

-   `image` (file, required) - Allowed: jpeg, jpg, png, webp (max 2048 KB)

**Parameters:**

-   `id` - News ID
-   `imageIndex` - Index of carousel image (0-based)

**Example:**

```javascript
const formData = new FormData();
formData.append("image", newImageFile);
formData.append("_method", "PUT");

fetch(`/api/finance/news/${newsId}/carousel/${imageIndex}`, {
    method: "POST",
    headers: {
        Authorization: `Bearer ${token}`,
    },
    body: formData,
});
```

**Response:** Same format as Get All News (updated news object)

**Error Response (404):**

```json
{
    "success": false,
    "message": "Carousel image not found at the specified index."
}
```

---

### 3. Delete Carousel Image

**DELETE** `/api/finance/news/{id}/carousel/{imageIndex}`

**Parameters:**

-   `id` - News ID
-   `imageIndex` - Index of carousel image (0-based)

**Example:**

```javascript
fetch(`/api/finance/news/${newsId}/carousel/${imageIndex}`, {
    method: "DELETE",
    headers: {
        Authorization: `Bearer ${token}`,
    },
});
```

**Response:** Same format as Get All News (updated news object)

**Error Response (404):**

```json
{
    "success": false,
    "message": "Carousel image not found at the specified index."
}
```

---

### 4. Stream Carousel Image

**GET** `/api/finance/news/carousel/{filename}`

**Example:**

```html
<img src="/api/finance/news/carousel/CAROUSEL_news_title_1.jpg" />
```

**Or use the URL from response:**

```javascript
// From carousel_images array in response
news.carousel_images[0].url;
// Output: "http://domain/storage/news_carousel/CAROUSEL_news_title_1.jpg"
```

---

## 📋 Validation Rules

### News Fields

-   `title`: string, max 255 characters, optional
-   `start_date`: date format (YYYY-MM-DD), optional
-   `end_date`: date format, must be >= start_date (if start_date provided), optional
-   `document`: file, allowed: pdf, doc, docx, jpg, jpeg, png, max 5000 KB, optional
-   `carousel_images[]`: array of files, each: jpeg, jpg, png, webp, max 2048 KB, optional

### Carousel Image Fields

-   `image`: file, allowed: jpeg, jpg, png, webp, max 2048 KB, required

### Important Notes

1. **Minimal requirement:** At least one of `title`, `document`, or `carousel_images` must be provided when creating news
2. **Image index:** Carousel image index starts from 0 (zero-based)
3. **File upload:** Use `FormData` for file uploads, not JSON
4. **Method spoofing:** For PUT/DELETE requests, use `POST` method with `_method` field or use actual PUT/DELETE method

---

## 🔄 Error Responses

### Validation Error (422)

```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "title": ["The title field is required."],
        "carousel_images.0": ["Each carousel image must be an image file."]
    }
}
```

### Not Found (404)

```json
{
    "success": false,
    "message": "Carousel image not found at the specified index."
}
```

### Unauthorized (401)

```json
{
    "message": "Unauthenticated."
}
```

---

## 💡 Frontend Implementation Tips

### 1. Display Carousel Images

```javascript
// React/Vue example
{
    news.carousel_images.map((image, index) => (
        <img key={index} src={image.url} alt={`Carousel ${index + 1}`} />
    ));
}
```

### 2. Upload Multiple Images

```javascript
const handleCarouselUpload = async (files) => {
    const formData = new FormData();

    Array.from(files).forEach((file) => {
        formData.append("carousel_images[]", file);
    });

    const response = await fetch("/api/finance/news/store", {
        method: "POST",
        headers: {
            Authorization: `Bearer ${token}`,
        },
        body: formData,
    });

    return response.json();
};
```

### 3. Delete Carousel Image with Confirmation

```javascript
const deleteCarouselImage = async (newsId, imageIndex) => {
    if (confirm("Are you sure you want to delete this image?")) {
        const response = await fetch(
            `/api/finance/news/${newsId}/carousel/${imageIndex}`,
            {
                method: "DELETE",
                headers: {
                    Authorization: `Bearer ${token}`,
                },
            }
        );

        return response.json();
    }
};
```

### 4. Update Single Carousel Image

```javascript
const updateCarouselImage = async (newsId, imageIndex, newImageFile) => {
    const formData = new FormData();
    formData.append("image", newImageFile);
    formData.append("_method", "PUT");

    const response = await fetch(
        `/api/finance/news/${newsId}/carousel/${imageIndex}`,
        {
            method: "POST",
            headers: {
                Authorization: `Bearer ${token}`,
            },
            body: formData,
        }
    );

    return response.json();
};
```

---

## 📝 Example: Complete News Management Flow

```javascript
// 1. Create news with carousel images only
const createNewsWithImages = async (imageFiles) => {
    const formData = new FormData();
    imageFiles.forEach((file) => {
        formData.append("carousel_images[]", file);
    });

    const response = await fetch("/api/finance/news/store", {
        method: "POST",
        headers: { Authorization: `Bearer ${token}` },
        body: formData,
    });

    return response.json();
};

// 2. Add more images to existing news
const addMoreImages = async (newsId, newImageFiles) => {
    const formData = new FormData();
    newImageFiles.forEach((file) => {
        formData.append("carousel_images[]", file);
    });
    formData.append("_method", "PUT");

    const response = await fetch(`/api/finance/news/update/${newsId}`, {
        method: "POST",
        headers: { Authorization: `Bearer ${token}` },
        body: formData,
    });

    return response.json();
};

// 3. Replace specific image
const replaceImage = async (newsId, imageIndex, newImage) => {
    const formData = new FormData();
    formData.append("image", newImage);
    formData.append("_method", "PUT");

    const response = await fetch(
        `/api/finance/news/${newsId}/carousel/${imageIndex}`,
        {
            method: "POST",
            headers: { Authorization: `Bearer ${token}` },
            body: formData,
        }
    );

    return response.json();
};

// 4. Delete specific image
const deleteImage = async (newsId, imageIndex) => {
    const response = await fetch(
        `/api/finance/news/${newsId}/carousel/${imageIndex}`,
        {
            method: "DELETE",
            headers: { Authorization: `Bearer ${token}` },
        }
    );

    return response.json();
};
```

---

## 🚀 Quick Reference

| Endpoint                                  | Method | Description           |
| ----------------------------------------- | ------ | --------------------- |
| `/api/finance/news`                       | GET    | Get all news          |
| `/api/finance/news/store`                 | POST   | Create news           |
| `/api/finance/news/edit/{id}`             | GET    | Get single news       |
| `/api/finance/news/update/{id}`           | PUT    | Update news           |
| `/api/finance/news/delete/{id}`           | DELETE | Delete news           |
| `/api/finance/news/{id}/carousel/upload`  | POST   | Upload carousel image |
| `/api/finance/news/{id}/carousel/{index}` | PUT    | Update carousel image |
| `/api/finance/news/{id}/carousel/{index}` | DELETE | Delete carousel image |
| `/api/finance/news/carousel/{filename}`   | GET    | Stream carousel image |
| `/api/finance/news/document/{filename}`   | GET    | Stream document       |

---

**Last Updated:** November 2024
**Version:** 1.0
