import sys
import json
import pickle
import pandas as pd
import hashlib

# --- Step 1: Consistent hashing for IP ---
def stable_hash(s):
    return int(hashlib.sha256(s.encode()).hexdigest(), 16) % 1000

# --- Step 2: Preprocess the input ---
def preprocess(data):
    device_type_map = {
        'Linux': 0,
        'Windows': 1,
        'Android': 2,
        'Mac': 3,
        'Tablet': 4,
        'Unknown': 5
    }
    device_type = device_type_map.get(data.get('device_type', 'Unknown'), 5)
    ip_hash = stable_hash(data.get('ip', ''))
    username_length = len(data.get('username', ''))

    return pd.DataFrame([{
        'device_type': device_type,
        'ip_hash': ip_hash,
        'username_length': username_length
    }])

# --- Step 3: Get JSON input ---
try:
    input_json = sys.argv[1]
    input_data = json.loads(input_json)
except Exception as e:
    print("normal")  # Fallback if bad input
    sys.exit(0)

# --- Step 4: Load model and predict ---
try:
    with open('model.pkl', 'rb') as f:
        model = pickle.load(f)
except Exception:
    print("normal")  # Fallback if model not found
    sys.exit(0)

df = preprocess(input_data)

try:
    prediction = model.predict(df)[0]
    print('suspicious' if prediction == 1 else 'normal')
except Exception:
    print("normal")  # Fallback on prediction failure
