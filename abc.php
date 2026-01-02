<?php
/**
 * STATION-X OMEGA v15.0 - THE FINAL STAND
 * Editor + File Manager + Smart Store + Terminal
 */

session_start();

// ==========================================
// 1. CẤU HÌNH BẢO MẬT (QUAN TRỌNG)
// ==========================================
$MY_IP = '42.113.171.143'; // Search "What is my IP" on Google
$HASH  = '$2y$10$.d9orPY6yAsW5FsklXnw..tSh6RxZRyCO7G.sY89cb50IoMuqKvAu'; 

// ==========================================
// 2. MÔI TRƯỜNG HỆ THỐNG
// ==========================================
$USER      = posix_getpwuid(posix_geteuid())['name'];
$HOME      = "/home/$USER";
$TOOLS     = "$HOME/my_tools";
$BIN_PATH  = "$TOOLS/bin";
$WORKSPACE = __DIR__ . "/workspace";

if (!file_exists($TOOLS)) mkdir($TOOLS, 0755, true);
if (!file_exists($BIN_PATH)) mkdir($BIN_PATH, 0755, true);
if (!file_exists($WORKSPACE)) mkdir($WORKSPACE, 0755, true);

// ==========================================
// 3. XỬ LÝ ĐĂNG NHẬP
// ==========================================
if ($_SERVER['REMOTE_ADDR'] !== $MY_IP) die("ACCESS_DENIED: " . $_SERVER['REMOTE_ADDR']);
if (isset($_GET['logout'])) { session_destroy(); header("Location: ?"); exit; }
if (isset($_POST['login']) && password_verify($_POST['pass'], $HASH)) $_SESSION['station_x'] = true;

if (!isset($_SESSION['station_x'])) {
    die('<body style="background:#020202;color:#0f0;text-align:center;padding-top:150px;font-family:monospace;">
        <form method="POST" style="border:2px solid #0f0;display:inline-block;padding:50px;background:#000;box-shadow:0 0 30px #0f0;">
            <h1 style="letter-spacing:10px;font-size:2em;">STATION-X_V15</h1><br>
            <input type="password" name="pass" autofocus style="background:#111;color:#0f0;border:1px solid #0f0;padding:15px;width:350px;outline:none;font-size:1.2em;"><br><br>
            <button name="login" style="background:#0f0;color:#000;padding:15px 50px;font-weight:bold;cursor:pointer;border:none;font-size:1em;letter-spacing:2px;">INITIATE_CORE_BOOT</button>
        </form></body>');
}

// ==========================================
// 4. CORE ACTIONS (AJAX ROUTER)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $gh_opts = ["http" => ["header" => "User-Agent: StationX\r\n"]];
    $ctx = stream_context_create($gh_opts);

    switch ($action) {
        // --- IDE: CHẠY CODE ---
        case 'execute':
            $lang = $_POST['lang']; $code = $_POST['code'];
            file_put_contents("$WORKSPACE/input.txt", $_POST['stdin'] ?? "");
            $res = "";
            $paths = [
                'node' => "$TOOLS/nodejs/bin/node",
                'java' => "$TOOLS/java/bin/java",
                'javac' => "$TOOLS/java/bin/javac",
                'lua' => "$TOOLS/lua/src/lua"
            ];

            if ($lang == 'cpp') {
                file_put_contents("$WORKSPACE/main.cpp", $code);
                shell_exec("g++ -O3 $WORKSPACE/main.cpp -o $WORKSPACE/app.out 2>&1");
                $res = shell_exec("timeout 5s $WORKSPACE/app.out < $WORKSPACE/input.txt 2>&1");
            } elseif ($lang == 'python') {
                file_put_contents("$WORKSPACE/s.py", $code);
                $res = shell_exec("python3 $WORKSPACE/s.py < $WORKSPACE/input.txt 2>&1");
            } elseif ($lang == 'javascript') {
                file_put_contents("$WORKSPACE/s.js", $code);
                $res = shell_exec("{$paths['node']} $WORKSPACE/s.js < $WORKSPACE/input.txt 2>&1");
            } elseif ($lang == 'java') {
                file_put_contents("$WORKSPACE/Main.java", $code);
                shell_exec("{$paths['javac']} $WORKSPACE/Main.java 2>&1");
                $res = shell_exec("{$paths['java']} -cp $WORKSPACE Main < $WORKSPACE/input.txt 2>&1");
            }
            echo $res ?: "> Execution finished (Empty Output)";
            break;

        // --- TERMINAL: BASH ---
        case 'terminal':
            $cmd = $_POST['cmd'];
            $lib_path = shell_exec("find $TOOLS -type d -name 'lib*' | tr '\n' ':'");
            $all_bin = shell_exec("find $TOOLS -type d -name 'bin' | tr '\n' ':'");
            $env = "export PATH=\$PATH:$BIN_PATH:$all_bin && export LD_LIBRARY_PATH=\$LD_LIBRARY_PATH:$lib_path && ";
            if (strpos($cmd, 'pip ') === 0) $cmd = str_replace('pip ', "python3 -m pip ", $cmd) . " --user";
            echo shell_exec($env . "cd " . escapeshellarg($_POST['path'] ?? $WORKSPACE) . " && $cmd 2>&1");
            break;

        // --- SMART STORE: SEARCH & INSTALL ---
        case 'search_gh':
            echo @file_get_contents("https://api.github.com/search/repositories?q=".urlencode($_POST['q']), false, $ctx);
            break;

        case 'install_gh':
            $repo = $_POST['repo']; $name = explode('/', $repo)[1];
            $rel_json = @file_get_contents("https://api.github.com/repos/$repo/releases/latest", false, $ctx);
            $done = false;
            
            if ($rel_json) {
                $rel = json_decode($rel_json, true);
                foreach($rel['assets'] as $asset) {
                    $aname = strtolower($asset['name']);
                    if ((strpos($aname, 'linux') !== false || strpos($aname, 'amd64') !== false) && (strpos($aname, '.tar') !== false || strpos($aname, '.gz') !== false)) {
                        $tmp = "$TOOLS/dl.tmp"; copy($asset['browser_download_url'], $tmp);
                        shell_exec("tar -xf $tmp -C $TOOLS"); unlink($tmp);
                        $done = true; break;
                    }
                }
            }
            if (!$done) { // SMART FALLBACK: TẢI RAW SCRIPT (Cho Neofetch...)
                $raw_urls = ["https://raw.githubusercontent.com/$repo/master/$name", "https://raw.githubusercontent.com/$repo/main/$name"];
                foreach($raw_urls as $url) {
                    if ($c = @file_get_contents($url, false, $ctx)) {
                        file_put_contents("$BIN_PATH/$name", $c);
                        shell_exec("chmod +x $BIN_PATH/$name");
                        $done = true; break;
                    }
                }
            }
            echo $done ? "SUCCESS: Installed $name" : "FAILED: No valid binary or script found";
            break;

        // --- FILE MANAGER: OPERATIONS ---
        case 'fm_list':
            $p = $_POST['path'];
            $items = [];
            foreach(array_diff(scandir($p), ['.']) as $f) {
                $full = realpath($p.'/'.$f);
                if (strpos($full, $HOME) === 0)
                    $items[] = ['name'=>$f, 'path'=>$full, 'is_dir'=>is_dir($full), 'size'=>@filesize($full)];
            }
            echo json_encode($items);
            break;

        case 'fm_save':
            file_put_contents($_POST['path'], $_POST['content']);
            echo "File saved successfully.";
            break;

        case 'fm_del':
            $p = $_POST['path'];
            is_dir($p) ? shell_exec("rm -rf " . escapeshellarg($p)) : unlink($p);
            echo "Deleted.";
            break;
    }
    exit;
}

// Download Trigger
if (isset($_GET['dl'])) {
    $f = realpath($_GET['dl']);
    if ($f && strpos($f, $HOME) === 0) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($f).'"');
        readfile($f); exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>STATION-X OMEGA v15.0</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.43.0/min/vs/editor/editor.main.min.css">
    <style>
        body { background: #010101; color: #00ff41; font-family: 'Fira Code', monospace; overflow: hidden; }
        .nexus-glass { background: rgba(5, 5, 5, 0.98); border: 1px solid #1a1a1a; }
        .tab-btn.active { color: #fff; border-bottom: 2px solid #00ff41; }
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-thumb { background: #111; }
        ::-webkit-scrollbar-thumb:hover { background: #0f0; }
        .file-item:hover { background: #111; color: #fff; cursor: pointer; }
    </style>
</head>
<body class="h-screen flex flex-col p-2">

    <!-- HEADER NAVIGATION -->
    <div class="h-12 flex items-center justify-between px-6 nexus-glass rounded-t-xl">
        <div class="flex items-center gap-6">
            <span class="font-black text-xl text-white tracking-tighter">STATION-X<span class="text-green-500">_OMEGA</span></span>
            <span class="text-[10px] opacity-30 hidden md:block uppercase tracking-widest">User: <?php echo $USER; ?> | Host: Server-205</span>
        </div>
        <div class="flex gap-6 items-center">
            <button onclick="setTab('ide')" id="t-ide" class="tab-btn active text-[10px] font-bold">EDITOR</button>
            <button onclick="setTab('fm')" id="t-fm" class="tab-btn text-[10px] font-bold">FILE_SYSTEM</button>
            <button onclick="setTab('store')" id="t-store" class="tab-btn text-[10px] font-bold">SMART_STORE</button>
            <button onclick="setTab('term')" id="t-term" class="tab-btn text-[10px] font-bold">TERMINAL</button>
            <a href="?logout=1" class="text-red-600 text-[10px] font-bold">[DISCONNECT]</a>
        </div>
    </div>

    <!-- MAIN INTERFACE -->
    <div class="flex-1 flex overflow-hidden nexus-glass border-t-0 rounded-b-xl">
        
        <!-- SIDEBAR: FILE TREE -->
        <div class="w-64 border-r border-gray-900 bg-black/40 flex flex-col">
            <div class="p-3 text-[9px] font-bold opacity-30 border-b border-gray-900 uppercase">Explorer_Tree</div>
            <div id="file-tree" class="flex-1 overflow-y-auto p-2 text-[11px] space-y-1"></div>
        </div>

        <!-- MAIN PANEL CONTENT -->
        <div class="flex-1 flex flex-col overflow-hidden">
            
            <!-- MODULE: EDITOR/IDE -->
            <div id="panel-ide" class="flex-1 flex flex-col">
                <div class="h-8 border-b border-gray-900 flex items-center px-4 justify-between bg-black/20">
                    <select id="lang" class="bg-transparent text-[10px] text-green-500 outline-none">
                        <option value="cpp">C++ (g++)</option>
                        <option value="python">Python 3</option>
                        <option value="javascript">Node.js</option>
                        <option value="java">Java 17</option>
                    </select>
                    <div class="flex gap-2">
                        <button onclick="runCode()" class="bg-green-700 text-black px-4 h-6 text-[9px] font-black rounded hover:bg-green-500">RUN_CODE</button>
                        <button onclick="saveFile()" id="save-btn" class="hidden bg-blue-700 text-white px-4 h-6 text-[9px] font-black rounded hover:bg-blue-500">SAVE_FILE</button>
                    </div>
                </div>
                <div id="editor-container" class="flex-1"></div>
            </div>

            <!-- MODULE: FILE MANAGER -->
            <div id="panel-fm" class="flex-1 hidden overflow-y-auto p-6 bg-black/10">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-white">File Manager</h2>
                    <div class="flex gap-2">
                        <input type="file" id="up-input" class="hidden" onchange="uploadFile()">
                        <button onclick="document.getElementById('up-input').click()" class="bg-blue-600 px-4 py-1 text-[10px] rounded text-white">UPLOAD</button>
                    </div>
                </div>
                <table class="w-full text-[11px] text-left">
                    <thead><tr class="opacity-30 border-b border-gray-900"><th>NAME</th><th>SIZE</th><th class="text-right">ACTIONS</th></tr></thead>
                    <tbody id="fm-table"></tbody>
                </table>
            </div>

            <!-- MODULE: SMART STORE -->
            <div id="panel-store" class="flex-1 hidden p-8 overflow-y-auto">
                <div class="max-w-2xl mx-auto">
                    <h2 class="text-2xl font-bold text-white mb-2">Smart App Store</h2>
                    <p class="text-[10px] text-gray-500 mb-8">Tải và cấu hình tự động Binary hoặc Raw Scripts từ GitHub.</p>
                    <div class="flex gap-2 mb-8">
                        <input type="text" id="store-q" placeholder="Search GitHub (vd: neofetch, node, ffmpeg...)" class="flex-1 bg-black border border-gray-800 p-3 rounded text-xs outline-none focus:border-green-500 text-white">
                        <button onclick="searchStore()" class="bg-green-600 text-black px-8 py-2 rounded text-xs font-bold">SEARCH</button>
                    </div>
                    <div id="store-results" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
                    <pre id="store-log" class="mt-6 p-4 bg-black border border-gray-800 text-[9px] text-gray-500 hidden h-40 overflow-y-auto font-mono"></pre>
                </div>
            </div>

            <!-- MODULE: TERMINAL -->
            <div id="panel-term" class="flex-1 hidden flex flex-col bg-black p-4">
                <div id="term-out" class="flex-1 overflow-y-auto font-mono text-[11px] text-gray-300">Welcome to Station Shell.</div>
                <div class="mt-2 flex gap-2 border-t border-gray-900 pt-3">
                    <span class="text-green-500">$</span>
                    <input type="text" id="term-in" class="bg-transparent outline-none text-[11px] w-full text-white" placeholder="Bash command...">
                </div>
            </div>

            <!-- GLOBAL FOOTER CONSOLE -->
            <div class="h-32 border-t border-gray-900 p-3 text-[10px] font-mono text-gray-400 overflow-y-auto whitespace-pre-wrap bg-black/40" id="global-out">
                > System Station Online.
            </div>
        </div>
    </div>

    <!-- CORE SCRIPTS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.43.0/min/vs/loader.min.js"></script>
    <script>
        let editor, curFile = "", curPath = "<?php echo $WORKSPACE; ?>";

        // Initialize Monaco
        require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.43.0/min/vs' }});
        require(['vs/editor/editor.main'], function() {
            editor = monaco.editor.create(document.getElementById('editor-container'), {
                value: "// Station Pro Initialized\n",
                language: 'javascript', theme: 'vs-dark', automaticLayout: true, fontSize: 13
            });
        });

        // Navigation
        function setTab(t) {
            document.querySelectorAll("[id^='panel-']").forEach(p => p.classList.add('hidden'));
            document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove('active'));
            document.getElementById('panel-' + t).classList.remove('hidden');
            document.getElementById('t-' + t).classList.add('active');
            if (t === 'fm') loadFM(curPath);
        }

        // File Manager
        function loadFM(path) {
            curPath = path;
            const fd = new FormData(); fd.append('action', 'fm_list'); fd.append('path', path);
            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                let html = "", tree = `<div class='opacity-40 mb-2 cursor-pointer hover:text-white' onclick="loadFM('<?php echo dirname($WORKSPACE); ?>')">.. [Parent Dir]</div>`;
                data.forEach(f => {
                    html += `<tr class="border-b border-gray-900/50 hover:bg-white/5">
                        <td class="py-2 cursor-pointer" onclick="${f.is_dir ? `loadFM('${f.path}')` : `openFile('${f.path}')`}">${f.is_dir ? '📁' : '📄'} ${f.name}</td>
                        <td class="opacity-40">${f.is_dir ? '--' : (f.size/1024).toFixed(1) + 'K'}</td>
                        <td class="text-right">
                            <a href="?dl=${encodeURIComponent(f.path)}" class="text-blue-500 mr-4">[GET]</a>
                            <button onclick="deleteItem('${f.path}')" class="text-red-700">[DEL]</button>
                        </td>
                    </tr>`;
                    tree += `<div class="file-item truncate p-1 rounded" onclick="${f.is_dir ? `loadFM('${f.path}')` : `openFile('${f.path}')`}">${f.is_dir ? '📁' : '📄'} ${f.name}</div>`;
                });
                document.getElementById('fm-table').innerHTML = html;
                document.getElementById('file-tree').innerHTML = tree;
            });
        }

        function openFile(path) {
            curFile = path; setTab('ide');
            document.getElementById('save-btn').classList.remove('hidden');
            const fd = new FormData(); fd.append('action', 'fm_list'); fd.append('path', path); // Reusing list to get content via fallback if needed or add separate action
            // For brevity, using direct fetch content helper
            fetch('', { method: 'POST', body: new URLSearchParams({action:'fm_save', path: path, get_content: 1}) })
            .then(r => r.text()).then(d => {
                editor.setValue(d);
                document.getElementById('global-out').innerText = "> Loaded File: " + path;
            });
        }

        function saveFile() {
            const fd = new FormData(); fd.append('action', 'fm_save');
            fd.append('path', curFile); fd.append('content', editor.getValue());
            fetch('', { method: 'POST', body: fd }).then(r => r.text()).then(d => { alert(d); });
        }

        // Compiler
        function runCode() {
            const out = document.getElementById('global-out');
            out.innerText = ">_ EXECUTING_BOOT...";
            const fd = new FormData();
            fd.append('action', 'execute');
            fd.append('lang', document.getElementById('lang').value);
            fd.append('code', editor.getValue());
            fd.append('stdin', "");
            fetch('', { method: 'POST', body: fd }).then(r => r.text()).then(d => { out.innerText = d; });
        }

        // App Store
        function searchStore() {
            const q = document.getElementById('store-q').value;
            const res = document.getElementById('store-results');
            res.innerHTML = "<div class='text-white text-xs'>Lục soát GitHub...</div>";
            const fd = new FormData(); fd.append('action', 'search_gh'); fd.append('q', q);
            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                res.innerHTML = "";
                data.items.slice(0, 6).forEach(repo => {
                    res.innerHTML += `<div class="nexus-glass p-4 rounded hover:border-green-500 transition-all flex flex-col justify-between">
                        <div>
                            <h4 class="font-bold text-white text-xs">${repo.full_name}</h4>
                            <p class="text-[9px] text-gray-500 mt-1 mb-3 h-8 overflow-hidden">${repo.description || '...'}</p>
                        </div>
                        <button onclick="installApp('${repo.full_name}')" class="bg-green-900/30 text-green-400 py-1 rounded text-[9px] font-bold hover:bg-green-600 hover:text-black">SMART_DEPLOY</button>
                    </div>`;
                });
            });
        }

        function installApp(repo) {
            const log = document.getElementById('store-log');
            log.classList.remove('hidden'); log.innerText = ">_ Deploying " + repo + "...";
            const fd = new FormData(); fd.append('action', 'install_gh'); fd.append('repo', repo);
            fetch('', { method: 'POST', body: fd }).then(r => r.text()).then(d => { log.innerText += "\n" + d; });
        }

        // Terminal
        document.getElementById('term-in').addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                const out = document.getElementById('term-out');
                const fd = new FormData(); fd.append('action', 'terminal'); fd.append('cmd', e.target.value); fd.append('path', curPath);
                fetch('', { method: 'POST', body: fd }).then(r => r.text()).then(d => {
                    out.innerHTML += `<div class='text-green-500 mt-2'>$ ${e.target.value}</div><pre class='text-white mb-2'>${d}</pre>`;
                    out.scrollTop = out.scrollHeight;
                });
                e.target.value = "";
            }
        });

        // Initialization
        loadFM(curPath);
    </script>
</body>
</html>
<?php 
// Content fetcher for editor helper
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_content'])) {
    if (strpos(realpath($_POST['path']), $HOME) === 0) echo file_get_contents($_POST['path']);
    exit;
}
?><?php
/**
 * OMEGA STATION v17.0 - THE AI NAVIGATOR
 * Editor + File Manager + AI Search + Terminal + Smart Installer
 */

session_start();

// ==========================================
// 1. CẤU HÌNH (BẮT BUỘC)
// ==========================================
$MY_IP = 'DIA_CHI_IP_CUA_BAN'; 
$HASH  = 'CHUOI_HASH_MAT_KHAU_CUA_BAN'; 
$GEMINI_API_KEY = 'PASTE_GEMINI_API_KEY_CUA_BAN'; // Lấy tại aistudio.google.com

$USER      = posix_getpwuid(posix_geteuid())['name'];
$HOME      = "/home/$USER";
$TOOLS     = "$HOME/my_tools";
$BIN_PATH  = "$TOOLS/bin";
$WORKSPACE = __DIR__ . "/workspace";

if (!file_exists($BIN_PATH)) mkdir($BIN_PATH, 0755, true);
if (!file_exists($WORKSPACE)) mkdir($WORKSPACE, 0755, true);

// ==========================================
// 2. ANSI COLOR FIXER (Dọn dẹp mã Terminal)
// ==========================================
function terminal_render($text) {
    $text = htmlspecialchars($text);
    $dict = [
        '[31m' => '<span style="color:#ff5555">', '[32m' => '<span style="color:#50fa7b">',
        '[33m' => '<span style="color:#f1fa8c">', '[34m' => '<span style="color:#8be9fd">',
        '[35m' => '<span style="color:#ff79c6">', '[36m' => '<span style="color:#8be9fd">',
        '[37m' => '<span style="color:#f8f8f2">', '[1m' => '<b>', '[0m' => '</span></b>',
    ];
    $text = str_replace(array_keys($dict), array_values($dict), $text);
    return preg_replace('/\\\\x1b\[[0-9;]*[mGKHJKLP]/', '', $text);
}

// ==========================================
// 3. BẢO MẬT & ĐĂNG NHẬP
// ==========================================
if ($_SERVER['REMOTE_ADDR'] !== $MY_IP) die("IP_DENIED");
if (isset($_GET['logout'])) { session_destroy(); header("Location: ?"); exit; }
if (isset($_POST['login']) && password_verify($_POST['pass'], $HASH)) $_SESSION['omega_v17'] = true;

if (!isset($_SESSION['omega_v17'])) {
    die('<body style="background:#020202;color:#0f0;text-align:center;padding-top:100px;font-family:monospace;">
        <form method="POST" style="border:1px solid #0f0;display:inline-block;padding:50px;background:#000;">
            <h1>[ OMEGA_v17_AUTH ]</h1><br>
            <input type="password" name="pass" autofocus style="background:#111;color:#0f0;border:1px solid #0f0;padding:12px;width:300px;"><br><br>
            <button name="login" style="background:#0f0;color:#000;padding:12px 30px;font-weight:bold;cursor:pointer;border:none;">BOOT_STATION</button>
        </form></body>');
}

// ==========================================
// 4. CORE ENGINE (AJAX ROUTER)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $gh_opts = ["http" => ["header" => "User-Agent: OmegaStation\r\n"]];
    $ctx = stream_context_create($gh_opts);

    switch ($action) {
        // --- AI NAVIGATOR: Dùng Gemini tìm Repo ---
        case 'ai_search':
            $q = $_POST['q'];
            $prompt = "Suggest 3 best GitHub repository paths (owner/name) for this tool description: '$q' for Linux x64. Format: Return ONLY 'owner/name' per line, no extra text.";
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$GEMINI_API_KEY";
            $postData = json_encode(["contents" => [["parts" => [["text" => $prompt]]]]]);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $res = curl_exec($ch);
            $res_json = json_decode($res, true);
            $repos = explode("\n", trim($res_json['candidates'][0]['content']['parts'][0]['text'] ?? ""));
            
            $results = [];
            foreach($repos as $r) {
                if(trim($r)) {
                    $repo_info = @file_get_contents("https://api.github.com/repos/".trim($r), false, $ctx);
                    if($repo_info) $results[] = json_decode($repo_info, true);
                }
            }
            echo json_encode($results);
            break;

        // --- SMART INSTALLER (Từ v15) ---
        case 'install_gh':
            $repo = $_POST['repo']; $name = explode('/', $repo)[1];
            echo "> Processing $repo...\n";
            $rel_json = @file_get_contents("https://api.github.com/repos/$repo/releases/latest", false, $ctx);
            $done = false;
            if ($rel_json) {
                $rel = json_decode($rel_json, true);
                foreach($rel['assets'] as $asset) {
                    $aname = strtolower($asset['name']);
                    if ((strpos($aname, 'linux') !== false || strpos($aname, 'amd64') !== false) && (strpos($aname, '.tar') !== false || strpos($aname, '.gz') !== false)) {
                        $tmp = "$TOOLS/dl.tmp"; copy($asset['browser_download_url'], $tmp);
                        shell_exec("tar -xf $tmp -C $TOOLS"); unlink($tmp);
                        $done = true; break;
                    }
                }
            }
            if (!$done) {
                $raw_url = "https://raw.githubusercontent.com/$repo/master/$name";
                if ($c = @file_get_contents($raw_url, false, $ctx)) {
                    file_put_contents("$BIN_PATH/$name", $c); shell_exec("chmod +x $BIN_PATH/$name");
                    $done = true;
                }
            }
            echo $done ? "SUCCESS" : "FAILED";
            break;

        // --- TERMINAL EXEC ---
        case 'terminal':
            $cmd = $_POST['cmd'];
            $env = "export HOME=$HOME && export XDG_CONFIG_HOME=$WORKSPACE && export PATH=\$PATH:$BIN_PATH:$(find $TOOLS -maxdepth 2 -type d | tr '\n' ':') && ";
            if(strpos($cmd, 'neofetch') !== false) $cmd .= " --off";
            echo terminal_render(shell_exec($env . "cd $WORKSPACE && $cmd 2>&1"));
            break;

        // --- IDE ACTIONS ---
        case 'fm_save':
            file_put_contents($_POST['path'], $_POST['content']); echo "Saved."; break;
        case 'fm_read':
            echo file_get_contents($_POST['path']); break;
        case 'fm_list':
            $p = $_POST['path']; $res = [];
            foreach(array_diff(scandir($p), ['.']) as $f) {
                $full = realpath($p.'/'.$f);
                if (strpos($full, $HOME) === 0) $res[] = ['name'=>$f, 'path'=>$full, 'is_dir'=>is_dir($full)];
            }
            echo json_encode($res); break;
        case 'execute':
            $lang = $_POST['lang']; $code = $_POST['code'];
            file_put_contents("$WORKSPACE/main_exec", $code);
            if($lang == 'cpp') {
                shell_exec("g++ -O3 $WORKSPACE/main_exec -o $WORKSPACE/a.out 2>&1");
                echo shell_exec("$WORKSPACE/a.out 2>&1");
            } elseif ($lang == 'python') echo shell_exec("python3 $WORKSPACE/main_exec 2>&1");
            break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OMEGA STATION v17.0 | AI NAVIGATOR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.43.0/min/vs/editor/editor.main.min.css">
    <style>
        body { background: #010101; color: #00ff41; font-family: 'Fira Code', monospace; overflow: hidden; }
        .glass { background: rgba(10, 10, 10, 0.98); border: 1px solid #1a1a1a; }
        .tab-btn.active { color: #fff; border-bottom: 2px solid #00ff41; }
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-thumb { background: #0f0; }
        .file-item:hover { background: #111; color: #fff; cursor: pointer; }
    </style>
</head>
<body class="h-screen flex flex-col p-2">

    <!-- NAV BAR -->
    <div class="h-12 flex items-center justify-between px-6 glass rounded-t-xl">
        <span class="font-black text-white italic">OMEGA<span class="text-green-500">_STATION_v17</span></span>
        <div class="flex gap-6 items-center">
            <button onclick="setTab('ide')" id="t-ide" class="tab-btn active text-[10px] font-bold">EDITOR</button>
            <button onclick="setTab('store')" id="t-store" class="tab-btn text-[10px] font-bold text-blue-400">AI_NAVIGATOR</button>
            <button onclick="setTab('fm')" id="t-fm" class="tab-btn text-[10px] font-bold">FILES</button>
            <button onclick="setTab('term')" id="t-term" class="tab-btn text-[10px] font-bold">TERMINAL</button>
            <a href="?logout=1" class="text-red-600 text-[10px]">[EXIT]</a>
        </div>
    </div>

    <div class="flex-1 flex overflow-hidden glass border-t-0 rounded-b-xl">
        
        <!-- SIDEBAR -->
        <div class="w-56 border-r border-gray-900 bg-black/40 flex flex-col overflow-y-auto" id="file-tree">
            <!-- Cây thư mục -->
        </div>

        <!-- CONTENT -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- IDE PANEL -->
            <div id="panel-ide" class="flex-1 flex flex-col">
                <div class="h-8 border-b border-gray-900 flex items-center px-4 justify-between bg-black/20">
                    <select id="lang" class="bg-transparent text-[10px] text-green-500">
                        <option value="cpp">C++</option>
                        <option value="python">Python</option>
                    </select>
                    <div class="flex gap-2">
                        <button onclick="runCode()" class="bg-green-700 text-black px-4 h-6 text-[9px] font-bold rounded">RUN</button>
                        <button onclick="saveFile()" id="save-btn" class="hidden bg-blue-700 text-white px-4 h-6 text-[9px] font-bold rounded">SAVE</button>
                    </div>
                </div>
                <div id="editor-container" class="flex-1"></div>
            </div>

            <!-- AI STORE PANEL -->
            <div id="panel-store" class="flex-1 hidden p-8 overflow-y-auto">
                <div class="max-w-2xl mx-auto">
                    <h2 class="text-2xl font-bold text-blue-400 mb-2 italic">AI_Navigator_Store</h2>
                    <p class="text-[10px] text-gray-500 mb-6">Mô tả công cụ ông cần, AI sẽ tìm Repo và cài đặt tự động.</p>
                    <div class="flex gap-2">
                        <input type="text" id="ai-q" placeholder="Ví dụ: Công cụ xem thông tin hệ thống, Bộ compiler cho Go..." class="flex-1 bg-black border border-gray-800 p-3 rounded text-sm text-white outline-none focus:border-blue-500">
                        <button onclick="aiSearch()" class="bg-blue-600 text-white px-8 py-2 rounded font-bold text-xs">SEARCH_AI</button>
                    </div>
                    <div id="ai-results" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8"></div>
                    <pre id="store-log" class="mt-8 p-4 bg-black border border-gray-900 text-[10px] text-gray-400 hidden h-40 overflow-y-auto"></pre>
                </div>
            </div>

            <!-- FM PANEL -->
            <div id="panel-fm" class="flex-1 hidden p-4 overflow-y-auto">
                <table class="w-full text-[11px] text-left">
                    <thead class="opacity-30 border-b border-gray-900"><tr><th>NAME</th><th>ACTION</th></tr></thead>
                    <tbody id="fm-table"></tbody>
                </table>
            </div>

            <!-- TERMINAL PANEL -->
            <div id="panel-term" class="flex-1 hidden flex flex-col bg-black p-4">
                <div id="term-out" class="flex-1 overflow-y-auto font-mono text-[11px] text-gray-300"></div>
                <div class="flex gap-2 border-t border-gray-900 pt-3">
                    <span class="text-green-500 font-bold">$</span>
                    <input type="text" id="term-in" class="bg-transparent outline-none text-white text-[11px] w-full" placeholder="Command...">
                </div>
            </div>

            <!-- CONSOLE FOOTER -->
            <div class="h-24 border-t border-gray-900 p-3 text-[10px] font-mono text-gray-400 overflow-y-auto bg-black/50" id="global-out">
                > Station Online. System: RedHat 8.5.
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.43.0/min/vs/loader.min.js"></script>
    <script>
        let editor, curFile = "", curPath = "<?php echo $WORKSPACE; ?>";

        require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.43.0/min/vs' }});
        require(['vs/editor/editor.main'], function() {
            editor = monaco.editor.create(document.getElementById('editor-container'), {
                value: "// Omega AI Station Ready.\n",
                language: 'javascript', theme: 'vs-dark', automaticLayout: true, fontSize: 13
            });
        });

        function setTab(t) {
            document.querySelectorAll("[id^='panel-']").forEach(p => p.classList.add('hidden'));
            document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove('active'));
            document.getElementById('panel-' + t).classList.remove('hidden');
            document.getElementById('t-' + t).classList.add('active');
            if(t === 'fm') loadFM(curPath);
        }

        function aiSearch() {
            const q = document.getElementById('ai-q').value;
            const res = document.getElementById('ai-results');
            res.innerHTML = "<div class='text-white col-span-2'>Gemini AI đang tìm kiếm Repo...</div>";
            const fd = new FormData(); fd.append('action', 'ai_search'); fd.append('q', q);
            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                res.innerHTML = "";
                data.forEach(repo => {
                    res.innerHTML += `<div class="glass p-4 rounded hover:border-blue-500 flex flex-col justify-between">
                        <div>
                            <h4 class="font-bold text-white text-xs">${repo.full_name}</h4>
                            <p class="text-[9px] text-gray-500 mt-2 mb-4 h-8 overflow-hidden">${repo.description || 'No desc'}</p>
                        </div>
                        <button onclick="installApp('${repo.full_name}')" class="bg-blue-900/30 text-blue-400 text-[10px] py-1 rounded font-bold hover:bg-blue-600">SMART_INSTALL</button>
                    </div>`;
                });
            });
        }

        function installApp(repo) {
            const log = document.getElementById('store-log');
            log.classList.remove('hidden'); log.innerText = "> Deploying " + repo + "...";
            const fd = new FormData(); fd.append('action', 'install_gh'); fd.append('repo', repo);
            fetch('', { method: 'POST', body: fd }).then(r => r.text()).then(d => { 
                log.innerText += "\n" + d; 
                if(d.includes("SUCCESS")) alert("Đã cài đặt xong!");
            });
        }

        function loadFM(path) {
            curPath = path;
            const fd = new FormData(); fd.append('action', 'fm_list'); fd.append('path', path);
            fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                let html = "", tree = "";
                data.forEach(f => {
                    html += `<tr class="border-b border-gray-900/50 hover:bg-white/5">
                        <td class="py-2 cursor-pointer" onclick="${f.is_dir ? `loadFM('${f.path}')` : `openFile('${f.path}')`}">${f.is_dir ? '📁' : '📄'} ${f.name}</td>
                        <td class="text-right"><a href="?dl=${encodeURIComponent(f.path)}" class="text-blue-500">[GET]</a></td>
                    </tr>`;
                    tree += `<div class="file-item truncate p-1" onclick="${f.is_dir ? `loadFM('${f.path}')` : `openFile('${f.path}')`}">${f.is_dir ? '📁' : '📄'} ${f.name}</div>`;
                });
                document.getElementById('fm-table').innerHTML = html;
                document.getElementById('file-tree').innerHTML = tree;
            });
        }

        function openFile(path) {
            curFile = path; setTab('ide');
            document.getElementById('save-btn').classList.remove('hidden');
            const fd = new FormData(); fd.append('action', 'fm_read'); fd.append('path', path);
            fetch('', { method: 'POST', body: fd }).then(r => r.text()).then(d => { editor.setValue(d); });
        }

        function saveFile() {
            const fd = new FormData(); fd.append('action', 'fm_save'); fd.append('path', curFile); fd.append('content', editor.getValue());
            fetch('', { method: 'POST', body: fd }).then(r => r.text()).then(d => { alert(d); });
        }

        function runCode() {
            const fd = new FormData(); fd.append('action', 'execute');
            fd.append('lang', document.getElementById('lang').value);
            fd.append('code', editor.getValue());
            fetch('', { method: 'POST', body: fd }).then(r => r.text()).then(d => { document.getElementById('global-out').innerText = d; });
        }

        document.getElementById('term-in').addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                const out = document.getElementById('term-out');
                const fd = new FormData(); fd.append('action', 'terminal'); fd.append('cmd', e.target.value);
                fetch('', { method: 'POST', body: fd }).then(r => r.text()).then(d => {
                    out.innerHTML += `<div class='text-green-500'>$ ${e.target.value}</div><div class='mb-2'>${d}</div>`;
                    out.scrollTop = out.scrollHeight;
                });
                e.target.value = "";
            }
        });

        loadFM(curPath);
    </script>
</body>
</html>