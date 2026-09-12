use rustfft::{num_complex::Complex, FftPlanner};
use std::io::{self, Read, Write};

const SAMPLE_RATE: f32 = 16000.0;
const N_FFT: usize = 512;
const WIN_LENGTH: usize = 400;
const HOP_LENGTH: usize = 160;
const N_MELS: usize = 80;
const F_MIN: f32 = 0.0;
const F_MAX: f32 = 8000.0;
const LOG_FLOOR: f32 = 1e-10;

fn hz_to_mel(hz: f32) -> f32 {
    2595.0 * (1.0 + hz / 700.0).log10()
}

fn mel_to_hz(mel: f32) -> f32 {
    700.0 * (10f32.powf(mel / 2595.0) - 1.0)
}

fn hann_window(len: usize) -> Vec<f32> {
    (0..len)
        .map(|i| 0.5 * (1.0 - (2.0 * std::f32::consts::PI * i as f32 / (len as f32 - 1.0)).cos()))
        .collect()
}

fn mel_filters() -> Vec<Vec<f32>> {
    let n_bins = N_FFT / 2 + 1;
    let mel_min = hz_to_mel(F_MIN);
    let mel_max = hz_to_mel(F_MAX);
    let mel_points: Vec<f32> = (0..N_MELS + 2)
        .map(|i| mel_min + (mel_max - mel_min) * i as f32 / (N_MELS as f32 + 1.0))
        .collect();
    let bin_points: Vec<usize> = mel_points
        .iter()
        .map(|m| ((mel_to_hz(*m) * N_FFT as f32) / SAMPLE_RATE).floor() as usize)
        .collect();

    let mut filters = vec![vec![0.0f32; n_bins]; N_MELS];
    for m in 1..=N_MELS {
        let left = bin_points[m - 1];
        let center = bin_points[m];
        let right = bin_points[m + 1];
        for k in left..center {
            if center == left || k >= n_bins {
                continue;
            }
            filters[m - 1][k] = (k - left) as f32 / (center - left) as f32;
        }
        for k in center..right {
            if right == center || k >= n_bins {
                continue;
            }
            filters[m - 1][k] = (right - k) as f32 / (right - center) as f32;
        }
    }
    filters
}

fn main() -> io::Result<()> {
    let mut buf = Vec::new();
    io::stdin().read_to_end(&mut buf)?;
    if buf.len() % 4 != 0 {
        eprintln!("stdin length not a multiple of 4 bytes");
        std::process::exit(1);
    }
    let waveform: Vec<f32> = buf
        .chunks_exact(4)
        .map(|c| f32::from_le_bytes([c[0], c[1], c[2], c[3]]))
        .collect();

    let window = hann_window(WIN_LENGTH);
    let filters = mel_filters();
    let n_bins = N_FFT / 2 + 1;

    let mut planner = FftPlanner::<f32>::new();
    let fft = planner.plan_fft_forward(N_FFT);

    let n_frames = if waveform.len() < WIN_LENGTH {
        0
    } else {
        1 + (waveform.len() - WIN_LENGTH) / HOP_LENGTH
    };

    let stdout = io::stdout();
    let mut out = stdout.lock();
    out.write_all(&(N_MELS as u32).to_le_bytes())?;
    out.write_all(&(n_frames as u32).to_le_bytes())?;

    let mut mel_matrix = vec![vec![0.0f32; n_frames]; N_MELS];

    let mut frame = vec![Complex::<f32> { re: 0.0, im: 0.0 }; N_FFT];
    for t in 0..n_frames {
        let start = t * HOP_LENGTH;
        for slot in frame.iter_mut() {
            slot.re = 0.0;
            slot.im = 0.0;
        }
        for i in 0..WIN_LENGTH {
            frame[i].re = waveform[start + i] * window[i];
        }
        fft.process(&mut frame);

        let power: Vec<f32> = (0..n_bins)
            .map(|k| frame[k].re * frame[k].re + frame[k].im * frame[k].im)
            .collect();
        for m in 0..N_MELS {
            let mut sum = 0.0f32;
            for k in 0..n_bins {
                sum += filters[m][k] * power[k];
            }
            mel_matrix[m][t] = sum.max(LOG_FLOOR).ln();
        }
    }

    for row in &mel_matrix {
        for &v in row {
            out.write_all(&v.to_le_bytes())?;
        }
    }
    Ok(())
}
