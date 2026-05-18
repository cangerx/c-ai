<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $template->title }} — {{ config('app.name', 'CANG-AI') }}</title>
    <style>
        :root {
            --bg: #f5f3f0; --panel: rgba(255,255,255,0.85); --line: rgba(0,0,0,0.06);
            --line-strong: rgba(0,0,0,0.1); --text: #1a1a1a; --muted: #6b7280;
            --muted-soft: #9ca3af; --accent: #2d5bf0; --accent-soft: rgba(45,91,240,0.08);
            --black: #111113; --radius: 14px;
        }
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; min-height: 100vh; }
        .shell { max-width: 720px; margin: 0 auto; padding: 20px 20px 80px; }

        .header { display: flex; align-items: center; gap: 12px; padding: 16px 0 24px; }
        .back { text-decoration: none; color: var(--muted); font-size: 14px; padding: 6px 12px; border-radius: 8px; border: 1px solid var(--line-strong); transition: all 0.2s; }
        .back:hover { color: var(--text); border-color: var(--accent); }

        .card { background: #fff; border-radius: var(--radius); border: 1px solid var(--line); padding: 28px; }
        .card h1 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .card .tags { margin-bottom: 16px; font-size: 12px; color: var(--muted-soft); }
        .card .tags span { display: inline-block; padding: 2px 8px; border-radius: 4px; background: var(--accent-soft); color: var(--accent); margin-right: 6px; }

        .preview-img { width: 100%; max-height: 320px; object-fit: cover; border-radius: 10px; margin-bottom: 20px; }

        .section-title { font-size: 13px; font-weight: 600; color: var(--muted); margin: 20px 0 12px; }

        .var-group { margin-bottom: 20px; }
        .var-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 4px; color: var(--text); }
        .var-group .var-desc { font-size: 12px; color: var(--muted-soft); margin-bottom: 8px; }

        .alt-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
        .alt-tag {
            padding: 6px 14px; border-radius: 999px; font-size: 13px;
            background: #f5f5f3; border: 1px solid var(--line-strong);
            cursor: pointer; transition: all 0.2s; user-select: none;
        }
        .alt-tag:hover { border-color: var(--accent); color: var(--accent); }
        .alt-tag.active { background: var(--accent); color: #fff; border-color: var(--accent); }

        .custom-input {
            width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--line-strong);
            font-size: 14px; outline: none; transition: border-color 0.2s;
        }
        .custom-input:focus { border-color: var(--accent); }
        .custom-input::placeholder { color: var(--muted-soft); }

        .upload-zone {
            border: 2px dashed var(--line-strong); border-radius: 12px; padding: 24px;
            text-align: center; cursor: pointer; transition: all 0.2s; position: relative;
        }
        .upload-zone:hover { border-color: var(--accent); background: var(--accent-soft); }
        .upload-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .upload-zone .icon { font-size: 28px; margin-bottom: 8px; color: var(--muted); font-weight: 300; }
        .upload-zone .text { font-size: 13px; color: var(--muted); }
        .upload-preview { max-width: 200px; max-height: 150px; border-radius: 8px; margin-top: 10px; display: none; }

        .prompt-preview { background: #f9f9f7; border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; font-size: 13px; line-height: 1.7; color: var(--text); margin: 16px 0; white-space: pre-wrap; word-break: break-all; min-height: 60px; }

        .btn-go {
            width: 100%; padding: 14px; border-radius: 12px; border: none;
            background: var(--black); color: #fff; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: opacity 0.2s; margin-top: 8px;
        }
        .btn-go:hover { opacity: 0.9; }

        .tip { text-align: center; margin-top: 10px; font-size: 12px; color: var(--muted-soft); }
    </style>
</head>
<body>
<div class="shell">
    <div class="header">
        <a href="/explore/templates" class="back">← 返回模板</a>
    </div>

    <div class="card">
        @if($template->preview_url)
            <img src="{{ $template->preview_url }}" alt="{{ $template->title }}" class="preview-img">
        @endif

        <h1>{{ $template->title }}</h1>
        @if($template->tags)
            <div class="tags">
                @foreach(explode(',', $template->tags) as $tag)
                    <span>{{ trim($tag) }}</span>
                @endforeach
            </div>
        @endif

        <div class="section-title">自定义参数</div>

        <div id="varsForm">
            @foreach($template->variables ?? [] as $var)
                <div class="var-group" data-var="{{ $var['name'] }}" data-type="{{ $var['type'] ?? 'text' }}">
                    <label>{{ $var['label'] ?? $var['name'] }}</label>
                    @if(!empty($var['description']))
                        <div class="var-desc">{{ $var['description'] }}</div>
                    @endif

                    @if(($var['type'] ?? 'text') === 'image')
                        <div class="upload-zone">
                            <input type="file" accept="image/*" data-var="{{ $var['name'] }}">
                            <div class="icon">+</div>
                            <div class="text">点击上传参考图片</div>
                            <img class="upload-preview">
                        </div>
                    @else
                        @if(!empty($var['alternatives']))
                            <div class="alt-tags">
                                @foreach($var['alternatives'] as $alt)
                                    <span class="alt-tag" data-value="{{ $alt }}">{{ $alt }}</span>
                                @endforeach
                            </div>
                        @endif
                        <input type="text" class="custom-input" placeholder="或输入自定义内容" data-var="{{ $var['name'] }}" value="">
                    @endif
                </div>
            @endforeach
        </div>

        <div class="section-title">提示词预览</div>
        <div class="prompt-preview" id="promptPreview">选择或填写参数后自动预览…</div>

        <button class="btn-go" id="goBtn">前往创作页生成</button>
        <div class="tip">点击后跳转到创作页，提示词将自动填入；如有参考图请在创作页上传</div>
    </div>
</div>

<script>
const tplPrompt = @json($template->template_prompt);
const hasImageVar = {{ collect($template->variables ?? [])->contains('type', 'image') ? 'true' : 'false' }};

// 图片上传预览
document.querySelectorAll('.upload-zone input[type="file"]').forEach(input => {
    input.addEventListener('change', () => {
        const preview = input.closest('.upload-zone').querySelector('.upload-preview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.style.display = 'none';
        }
        updatePreview();
    });
});

// 选项标签点击
document.querySelectorAll('.alt-tag').forEach(tag => {
    tag.addEventListener('click', () => {
        const group = tag.closest('.var-group');
        group.querySelectorAll('.alt-tag').forEach(t => t.classList.remove('active'));
        tag.classList.add('active');
        group.querySelector('.custom-input').value = '';
        updatePreview();
    });
});

// 自定义输入时取消选项高亮
document.querySelectorAll('.custom-input').forEach(input => {
    input.addEventListener('input', () => {
        const group = input.closest('.var-group');
        if (input.value.trim()) {
            group.querySelectorAll('.alt-tag').forEach(t => t.classList.remove('active'));
        }
        updatePreview();
    });
});

function getVarValue(group) {
    if (group.dataset.type === 'image') {
        const input = group.querySelector('input[type="file"]');
        return input.files && input.files[0] ? '[已上传图片]' : '[待上传图片]';
    }
    const input = group.querySelector('.custom-input');
    if (input.value.trim()) return input.value.trim();
    const active = group.querySelector('.alt-tag.active');
    if (active) return active.dataset.value;
    return '';
}

function buildPrompt() {
    let prompt = tplPrompt;
    document.querySelectorAll('.var-group[data-var]').forEach(group => {
        const name = group.dataset.var;
        const val = getVarValue(group);
        prompt = prompt.replaceAll('{{' + name + '}}', val || '[未填写]');
    });
    return prompt;
}

function updatePreview() {
    document.getElementById('promptPreview').textContent = buildPrompt();
}
updatePreview();

document.getElementById('goBtn').addEventListener('click', () => {
    const prompt = buildPrompt().trim();
    if (!prompt) return;
    // 如果有图片变量，存储到 sessionStorage 供首页读取
    const imageFiles = [];
    document.querySelectorAll('.var-group[data-type="image"] input[type="file"]').forEach(input => {
        if (input.files && input.files[0]) imageFiles.push(input.files[0]);
    });
    if (imageFiles.length) {
        sessionStorage.setItem('tpl_has_image', '1');
    }
    let url = '/?prompt=' + encodeURIComponent(prompt);
    if (hasImageVar) url += '&mode=image';
    window.location.href = url;
});
</script>
</body>
</html>
