// Nạp các thư viện cần thiết
require('dotenv').config(); // Phải gọi đầu tiên để nạp biến môi trường từ file .env
const express = require('express');
const axios = require('axios');
const cors = require('cors');

// Khởi tạo ứng dụng Express
const app = express();
const PORT = 3000; // Server sẽ chạy ở cổng 3000

// Cấu hình CORS để cho phép trang web của bạn gọi đến
app.use(cors());

// =============================================================
// ==== ENDPOINT GỐC CỦA BẠN - ĐÃ THÊM BẢO VỆ CHỐNG LỖI MAP ====
// =============================================================
app.get('/geoserver/api/search-images', async (req, res) => {
    const searchQuery = req.query.q;
    if (!searchQuery) {
        return res.status(400).json({ error: 'Thiếu tham số tìm kiếm (q)' });
    }

    const apiKey = process.env.GOOGLE_API_KEY;
    const cxId = process.env.GOOGLE_CX_ID;
    const googleApiUrl = `https://www.googleapis.com/customsearch/v1?key=${apiKey}&cx=${cxId}&q=${encodeURIComponent(searchQuery)}&searchType=image&num=3`;

    try {
        const response = await axios.get(googleApiUrl);

        // **FIX**: Kiểm tra xem "items" có tồn tại không trước khi gọi .map()
        // Nếu response.data.items tồn tại, thì map nó. Nếu không, trả về một mảng rỗng [].
        const imageUrls = response.data.items ? response.data.items.map(item => item.link) : [];

        res.json({ imageUrls });

    } catch (error) {
        // Log lỗi chi tiết hơn để dễ gỡ lỗi
        console.error("Lỗi khi gọi Google API (search-images):", error.response ? error.response.data : error.message);
        res.status(500).json({ error: 'Có lỗi xảy ra phía server' });
    }
});

// =============================================================
// ==== ENDPOINT MỚI - ĐÃ THÊM BẢO VỆ CHỐNG LỖI MAP ====
// =============================================================
app.get('/geoserver/api/search2', async (req, res) => {
    const searchQuery = req.query.q;
    if (!searchQuery) {
        return res.status(400).json({ error: 'Thiếu tham số tìm kiếm (q)' });
    }

    const apiKey = process.env.GOOGLE_API_KEY;
    const cxId = process.env.GOOGLE_CX_ID_2; // Sử dụng CX ID thứ hai

    if (!cxId) {
        return res.status(500).json({ error: 'GOOGLE_CX_ID_2 chưa được cấu hình trong file .env' });
    }
    
    const googleApiUrl = `https://www.googleapis.com/customsearch/v1?key=${apiKey}&cx=${cxId}&q=${encodeURIComponent(searchQuery)}&searchType=image&num=10`;

    try {
        const response = await axios.get(googleApiUrl);

        // **FIX**: Áp dụng cùng một logic kiểm tra ở đây
        const imageUrls = response.data.items ? response.data.items.map(item => item.link) : [];
        
        res.json({ imageUrls });

    } catch (error) {
        console.error("Lỗi khi gọi Google API (search2):", error.response ? error.response.data : error.message);
        res.status(500).json({ error: 'Có lỗi xảy ra phía server' });
    }
});


// Khởi động server
app.listen(PORT, () => {
    console.log(`Server trung gian đang chạy tại http://localhost:${PORT}`);
    console.log(`Endpoint 1: /geoserver/api/search-images`);
    console.log(`Endpoint 2: /geoserver/api/search2`);
});