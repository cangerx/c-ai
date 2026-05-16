<?php

namespace App\Services;

class ContentFilterService
{
    protected array $words = [
        // 政治敏感
        '习近平','毛泽东','邓小平','江泽民','胡锦涛','共产党','国民党','天安门事件','六四',
        '法轮功','藏独','疆独','台独','港独','民运','反共','颠覆政权','推翻政府',
        '文化大革命','大跃进','反右','六四事件','天安门广场','学生运动',

        // 色情
        '裸体','性交','做爱','口交','肛交','自慰','手淫','阴茎','阴道','乳房',
        '色情','黄片','A片','AV女优','成人视频','淫荡','骚逼','鸡巴',
        '强奸','轮奸','性奴','幼女','恋童','未成年','儿童色情',

        // 暴力恐怖
        '杀人','砍人','爆炸','炸弹','恐怖袭击','自杀','割腕','跳楼',
        '枪支','制枪','炸药配方','制毒','冰毒','海洛因','大麻种植',

        // 违法犯罪
        '诈骗教程','洗钱','贩毒','人口贩卖','偷渡','假币','伪造证件',
        '黑客攻击','DDOS','木马病毒','钓鱼网站','盗号',

        // 歧视仇恨
        '种族灭绝','纳粹','希特勒','白人至上','杀光',

        // 英文关键词
        'nude','naked','porn','sex','fuck','dick','pussy','rape',
        'child porn','cp','loli','shota','kill','bomb','terrorist',
        'suicide method','how to make explosives','drug recipe',
    ];

    public function check(string $prompt): ?string
    {
        $lower = mb_strtolower($prompt);

        foreach ($this->words as $word) {
            if (mb_stripos($lower, mb_strtolower($word)) !== false) {
                return $word;
            }
        }

        return null;
    }

    public function isClean(string $prompt): bool
    {
        return $this->check($prompt) === null;
    }
}
